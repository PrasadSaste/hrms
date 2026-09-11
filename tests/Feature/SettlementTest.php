<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\Settlement;
use App\Services\PayrollService;
use App\Services\SettlementService;
use App\Support\Roles;
use App\Support\SettlementLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * What somebody is owed on their last day.
 *
 * Every figure here is one an employee can and does query, sometimes a year
 * later, so each rule is pinned separately rather than checked as a total.
 */
class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected SettlementService $settlements;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));
        $this->settlements = app(SettlementService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Somebody on a known salary, joined on a known day. */
    protected function leaver(string $joined = '2018-04-01', float $ctc = 1200000, array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse($joined),
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => $joined,
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        app(PayrollService::class)->applyDefaultComponents($structure);

        return $employee->fresh();
    }

    protected function lines(Employee $employee, string $lastDay): array
    {
        return collect($this->settlements->preview($employee, Carbon::parse($lastDay))['lines'])
            ->keyBy('key')
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | The final month
    |--------------------------------------------------------------------------
    */

    public function test_a_full_final_month_pays_a_full_month(): void
    {
        $employee = $this->leaver();
        $gross = $employee->salaryStructureOn()->totalEarnings();

        $lines = $this->lines($employee, '2026-09-30');

        // Not the twenty-six day rate: that would pay 30/26 of a month's
        // salary, which is fifteen per cent too much.
        $this->assertSame($gross, $lines[SettlementLines::FINAL_SALARY]['amount']);
    }

    public function test_half_a_month_pays_half_a_month(): void
    {
        $employee = $this->leaver();
        $gross = $employee->salaryStructureOn()->totalEarnings();

        $lines = $this->lines($employee, '2026-10-15');

        $this->assertSame(round($gross * 15 / 31, 2), $lines[SettlementLines::FINAL_SALARY]['amount']);
    }

    public function test_a_month_payroll_already_paid_is_not_paid_twice(): void
    {
        $employee = $this->leaver();

        $this->assertArrayHasKey(
            SettlementLines::FINAL_SALARY,
            $this->lines($employee, '2026-09-30'),
        );

        // Payroll publishes September; the settlement must stop claiming it.
        $payroll = Payroll::create([
            'reference' => 'PR-TEST-1',
            'company_id' => $employee->company_id,
            'title' => 'September 2026',
            'month' => 9,
            'year' => 2026,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'payment_date' => '2026-09-30',
            'status' => 'approved',
        ]);

        $employee->payslips()->create([
            'slip_number' => 'PS-TEST-1',
            'payroll_id' => $payroll->id,
            'company_id' => $employee->company_id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'working_days' => 30,
            'paid_days' => 30,
            'basic_salary' => 40000,
            'gross_earnings' => 100000,
            'total_deductions' => 0,
            'net_pay' => 100000,
            'currency' => 'INR',
            'status' => 'published',
        ]);

        $this->assertArrayNotHasKey(
            SettlementLines::FINAL_SALARY,
            $this->lines($employee->fresh(), '2026-09-30'),
            'A settlement that quietly pays September twice is worse than one that leaves it out.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Gratuity
    |--------------------------------------------------------------------------
    */

    public function test_gratuity_is_fifteen_days_a_year_over_twenty_six(): void
    {
        $employee = $this->leaver('2018-04-01');
        $basic = $this->settlements->preview($employee, Carbon::parse('2026-09-30'))['last_drawn_basic'];

        $lines = $this->lines($employee, '2026-09-30');

        // Eight completed years on the Payment of Gratuity Act formula.
        $this->assertSame(
            round($basic * 15 / 26 * 8, 2),
            $lines[SettlementLines::GRATUITY]['amount'],
        );
    }

    public function test_nobody_under_five_years_gets_gratuity(): void
    {
        $employee = $this->leaver('2023-01-01');

        $this->assertArrayNotHasKey(
            SettlementLines::GRATUITY,
            $this->lines($employee, '2026-09-30'),
            'The Act says five completed years.',
        );

        $this->assertFalse($this->settlements->preview($employee, Carbon::parse('2026-09-30'))['gratuity_eligible']);
    }

    public function test_gratuity_stops_at_the_statutory_ceiling(): void
    {
        // A basic large enough that the formula would run past the cap.
        $employee = $this->leaver('2000-01-01', 90000000);

        $lines = $this->lines($employee, '2026-09-30');

        $this->assertSame(
            SettlementService::GRATUITY_CAP,
            $lines[SettlementLines::GRATUITY]['amount'],
        );
        $this->assertStringContainsString('capped', $lines[SettlementLines::GRATUITY]['basis']);
    }

    /*
    |--------------------------------------------------------------------------
    | Leave encashment
    |--------------------------------------------------------------------------
    */

    public function test_only_leave_that_carries_forward_is_encashed(): void
    {
        $employee = $this->leaver();
        $year = 2026;

        $carries = LeaveType::where('carry_forward', true)->firstOrFail();
        $lapses = LeaveType::where('carry_forward', false)->where('is_paid', true)->firstOrFail();

        foreach ([[$carries, 8], [$lapses, 5]] as [$type, $days]) {
            LeaveAllocation::updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['allocated_days' => $days, 'used_days' => 0, 'carried_forward_days' => 0, 'encashed_days' => 0],
            );
        }

        // The eight that carry forward, not the thirteen on the books: an
        // allowance that lapses in December does not become money in
        // September because somebody left.
        $this->assertSame(8.0, $this->settlements->encashableDays($employee, Carbon::parse('2026-09-30')));

        $lines = $this->lines($employee, '2026-09-30');
        $basic = $this->settlements->preview($employee, Carbon::parse('2026-09-30'))['last_drawn_basic'];

        // Rate first, then multiply — not the other way round. The statement
        // says "8 days at ₹1,538.46", and 8 × 1,538.46 has to be the number
        // printed beside it or the first person with a calculator disputes it.
        $this->assertSame(
            round(round($basic / 26, 2) * 8, 2),
            $lines[SettlementLines::LEAVE_ENCASHMENT]['amount'],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Preparing and approving
    |--------------------------------------------------------------------------
    */

    public function test_preparing_freezes_the_bases_and_the_lines(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->leaver();

        $settlement = $this->settlements->prepare($employee, ['last_working_day' => '2026-09-30'], $hr->user);

        $this->assertStringStartsWith('SET/2026/', $settlement->reference);
        $this->assertSame(Settlement::DRAFT, $settlement->status);
        $this->assertGreaterThan(0, $settlement->last_drawn_basic);
        $this->assertSame(8.5, round($settlement->service_years, 1));
        $this->assertTrue($settlement->gratuity_eligible);
        $this->assertTrue($settlement->lines->isNotEmpty());

        // The reasoning is kept beside the number, because "Gratuity
        // ₹9,23,077" is not a defensible line on its own.
        $this->assertNotEmpty($settlement->lines->firstWhere('key', SettlementLines::GRATUITY)->basis);
    }

    public function test_the_totals_add_up_and_can_come_out_negative(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver('2024-01-01'), ['last_working_day' => '2026-09-30'], $hr->user);

        $earnings = $settlement->total_earnings;

        $this->settlements->addLine($settlement, [
            'key' => SettlementLines::ASSET_RECOVERY,
            'label' => 'Laptop not returned',
            'amount' => $earnings + 5000,
            'basis' => 'One laptop still outstanding.',
        ]);

        $settlement = $settlement->fresh('lines');

        $this->assertSame(round($earnings - ($earnings + 5000), 2), $settlement->net_payable);
        $this->assertTrue($settlement->isRecoverable());
        $this->assertSame('Recoverable from them', $settlement->netLabel());
    }

    public function test_an_approved_settlement_cannot_be_changed(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        $this->settlements->approve($settlement, $hr->user);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be changed');

        $this->settlements->addLine($settlement->fresh(), [
            'key' => SettlementLines::BONUS, 'label' => 'A late thought', 'amount' => 1000,
        ]);
    }

    public function test_it_must_be_approved_before_it_can_be_paid(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Approve the settlement');

        $this->settlements->markPaid($settlement);
    }

    public function test_nobody_gets_two_settlements(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->leaver();

        $this->settlements->prepare($employee, ['last_working_day' => '2026-09-30'], $hr->user);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already has a settlement');

        $this->settlements->prepare($employee->fresh(), ['last_working_day' => '2026-09-30'], $hr->user);
    }

    /*
    |--------------------------------------------------------------------------
    | The screens
    |--------------------------------------------------------------------------
    */

    public function test_every_settlement_screen_renders(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        foreach ([
            route('settlements.index'),
            route('settlements.create'),
            route('settlements.create', ['employee_id' => $settlement->employee_id]),
            route('settlements.show', $settlement),
        ] as $url) {
            $this->actingAs($hr->user)->get($url)->assertOk();
        }
    }

    public function test_the_statement_downloads_as_a_pdf(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        $response = $this->actingAs($hr->user)->get(route('settlements.download', $settlement))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_employee_sees_their_own_only_once_it_is_approved(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->leaver();
        $settlement = $this->settlements->prepare($employee, ['last_working_day' => '2026-09-30'], $hr->user);

        // A draft is somebody's working out, not a statement.
        $this->actingAs($employee->user)->get(route('my-settlement'))->assertNotFound();

        $this->settlements->approve($settlement, $hr->user);

        $this->actingAs($employee->user)->get(route('my-settlement'))
            ->assertOk()
            ->assertSee($settlement->reference);
    }

    public function test_one_employee_cannot_read_another_settlement(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $stranger = $this->makeEmployee();
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        $this->actingAs($stranger->user)->get(route('settlements.show', $settlement))->assertForbidden();
        $this->actingAs($stranger->user)->get(route('settlements.download', $settlement))->assertForbidden();
    }

    public function test_preparing_and_approving_are_separate_permissions(): void
    {
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        // An accountant prepares the figures; signing them off is somebody
        // else's job.
        $this->assertTrue($accountant->user->can('settlements.manage'));
        $this->assertFalse($accountant->user->can('settlements.approve'));

        $this->actingAs($accountant->user)
            ->post(route('settlements.approve', $settlement))
            ->assertForbidden();
    }

    public function test_a_line_can_be_added_and_taken_off_a_draft(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);
        $before = $settlement->total_deductions;

        $this->actingAs($hr->user)->post(route('settlements.lines.store', $settlement), [
            'key' => SettlementLines::ASSET_RECOVERY,
            'label' => 'Laptop not returned',
            'amount' => 45000,
            'basis' => 'One MacBook still outstanding.',
        ])->assertRedirect();

        $settlement = $settlement->fresh('lines');
        $this->assertSame($before + 45000, $settlement->total_deductions);

        $line = $settlement->lines->firstWhere('key', SettlementLines::ASSET_RECOVERY);

        $this->actingAs($hr->user)
            ->delete(route('settlements.lines.destroy', [$settlement, $line]))
            ->assertRedirect();

        $this->assertSame($before, $settlement->fresh()->total_deductions);
    }

    public function test_a_computed_line_cannot_be_added_by_hand(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $settlement = $this->settlements->prepare($this->leaver(), ['last_working_day' => '2026-09-30'], $hr->user);

        // Gratuity is worked out from the record; typing one in would let
        // somebody quietly overrule the statutory formula.
        $this->actingAs($hr->user)->post(route('settlements.lines.store', $settlement), [
            'key' => SettlementLines::GRATUITY,
            'amount' => 999999,
        ])->assertSessionHasErrors('key');
    }

    public function test_service_is_measured_in_completed_length_not_calendar_years(): void
    {
        $employee = $this->leaver('2021-10-01');

        // Just under five years: not eligible, however it is rounded.
        $this->assertLessThan(5.0, $this->settlements->serviceYears($employee, Carbon::parse('2026-09-30')));

        $this->assertGreaterThanOrEqual(5.0, $this->settlements->serviceYears($employee, Carbon::parse('2026-10-05')));
    }
}
