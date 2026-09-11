<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveAccrual;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Services\LeaveAccrualService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Leave earned a month at a time, at one rate for confirmed staff and a lower
 * one while somebody is on probation.
 */
class LeaveAccrualTest extends TestCase
{
    use RefreshDatabase;

    protected LeaveAccrualService $accruals;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00'));
        $this->accruals = app(LeaveAccrualService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Earned Leave at 1.5 a month, 1 on probation, crediting from January. */
    protected function monthlyType(array $attributes = []): LeaveType
    {
        $this->seedReferenceData();

        $type = LeaveType::where('code', 'EL')->firstOrFail();

        $type->update(array_merge([
            'accrual' => LeaveType::ACCRUAL_MONTHLY,
            'days_per_month' => 1.5,
            'probation_days_per_month' => 1.0,
            'accrue_in_advance' => false,
            'accrual_starts_on' => '2026-01-01',
            'days_per_year' => 0,
            'applicable_after_months' => 0,
        ], $attributes));

        return $type->fresh();
    }

    protected function credited(Employee $employee, LeaveType $type): float
    {
        return (float) LeaveAccrual::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->sum('days');
    }

    // ----------------------------------------------------------- the rates

    public function test_a_confirmed_employee_earns_the_full_rate(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2020-01-01',
            'employment_status' => 'permanent',
        ]);

        $this->accruals->accrue();

        // January to August: September is not over, so it has not been earned.
        $this->assertSame(12.0, $this->credited($employee, $type));
        $this->assertSame(8, LeaveAccrual::where('employee_id', $employee->id)->count());
    }

    public function test_somebody_on_probation_earns_the_lower_rate(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2020-01-01',
            'employment_status' => 'probation',
        ]);

        $this->accruals->accrue();

        $this->assertSame(8.0, $this->credited($employee, $type));
        $this->assertSame(
            LeaveAccrual::BASIS_PROBATION,
            LeaveAccrual::where('employee_id', $employee->id)->first()->basis,
        );
    }

    public function test_the_rate_changes_from_the_month_they_were_confirmed(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2026-01-01',
            'employment_status' => 'permanent',
            'date_of_confirmation' => '2026-07-15',
        ]);

        $this->accruals->accrue();

        $byMonth = LeaveAccrual::where('employee_id', $employee->id)->get()->keyBy('month');

        // Confirmed on 15 July, so July is already at the full rate and June is not.
        $this->assertSame(1.0, $byMonth[6]->days);
        $this->assertSame(1.5, $byMonth[7]->days);
        $this->assertSame(LeaveAccrual::BASIS_PROBATION, $byMonth[6]->basis);
        $this->assertSame(LeaveAccrual::BASIS_PERMANENT, $byMonth[7]->basis);
    }

    public function test_probation_earns_the_standard_rate_when_no_probation_rate_is_set(): void
    {
        // An empty box means "not configured", not "nothing".
        $type = $this->monthlyType(['probation_days_per_month' => null]);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2020-01-01',
            'employment_status' => 'probation',
        ]);

        $this->accruals->accrue();

        $this->assertSame(12.0, $this->credited($employee, $type));
    }

    // ------------------------------------------------------- part months

    public function test_a_mid_month_joiner_is_credited_for_the_part_they_were_here(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2026-06-20',
            'employment_status' => 'permanent',
        ]);

        $this->accruals->accrue();

        $june = LeaveAccrual::where('employee_id', $employee->id)->where('month', 6)->first();

        // 11 of June's 30 days, at 1.5 a month.
        $this->assertEqualsWithDelta(0.55, $june->days, 0.01);
        $this->assertTrue($june->isPartial());
        $this->assertSame(1.5, LeaveAccrual::where('employee_id', $employee->id)->where('month', 7)->first()->days);
    }

    public function test_nothing_is_credited_for_a_month_before_they_joined(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2026-06-20']);

        $this->accruals->accrue();

        $this->assertSame(0, LeaveAccrual::where('employee_id', $employee->id)->where('month', 5)->count());
    }

    public function test_a_leaver_earns_up_to_their_last_day(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => '2026-01-01',
            'date_of_exit' => '2026-03-10',
            'employment_status' => 'permanent',
        ]);

        $credit = $this->accruals->creditFor($employee, $type, Carbon::create(2026, 3, 1));

        // 10 of March's 31 days.
        $this->assertEqualsWithDelta(0.48, $credit['days'], 0.01);
        $this->assertNull($this->accruals->creditFor($employee, $type, Carbon::create(2026, 4, 1)));
    }

    // ----------------------------------------------------- running it again

    public function test_running_it_twice_credits_nothing_the_second_time(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $first = $this->accruals->accrue();
        $second = $this->accruals->accrue();

        $this->assertSame(8, $first['credited']);
        $this->assertSame(0, $second['credited'], 'A month already paid must never be paid again.');
        $this->assertSame(12.0, $this->credited($employee, $type));
    }

    public function test_a_month_the_server_missed_is_caught_up(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        // Only up to March ran; April onwards never did.
        $this->accruals->accrue(Carbon::parse('2026-04-05'));
        $this->assertSame(3, LeaveAccrual::where('employee_id', $employee->id)->count());

        $this->accruals->accrue();

        $this->assertSame(8, LeaveAccrual::where('employee_id', $employee->id)->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $result = $this->accruals->accrue(null, null, dryRun: true);

        $this->assertSame(8, $result['credited']);
        $this->assertSame(0, LeaveAccrual::where('employee_id', $employee->id)->count());
    }

    // ------------------------------------------------------- the balance

    public function test_the_credits_add_up_on_the_balance(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', 2026)
            ->first();

        $this->assertSame(12.0, $allocation->allocated_days);
    }

    public function test_an_opening_balance_carried_from_an_old_system_survives(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        LeaveAllocation::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026],
            ['allocated_days' => 5],
        );

        $this->accruals->accrue();

        // Credits are added, never recalculated over the top.
        $this->assertSame(17.0, LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)->where('year', 2026)->value('allocated_days'));
    }

    public function test_a_monthly_type_grants_nothing_up_front(): void
    {
        $type = $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2026-09-01']);

        // The employee was created with the usual allocations seeded.
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->first();

        $this->assertSame(0.0, $allocation?->allocated_days ?? 0.0,
            'A monthly type must not hand over a year of leave on day one.');
    }

    public function test_the_annual_ceiling_stops_the_credits(): void
    {
        $type = $this->monthlyType(['days_per_year' => 4]);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        $this->assertSame(4.0, $this->credited($employee, $type), 'The year is capped at four days.');
    }

    public function test_nothing_before_the_start_date_is_credited(): void
    {
        $type = $this->monthlyType(['accrual_starts_on' => '2026-07-01']);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        // July and August only: switching a type over in July does not hand
        // everybody the first half of the year.
        $this->assertSame(3.0, $this->credited($employee, $type));
    }

    public function test_credited_in_advance_pays_for_the_month_that_has_started(): void
    {
        $type = $this->monthlyType(['accrue_in_advance' => true]);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        // January to September inclusive, because September has begun.
        $this->assertSame(13.5, $this->credited($employee, $type));
    }

    public function test_a_yearly_type_is_left_alone(): void
    {
        $this->seedReferenceData();
        LeaveType::query()->update(['accrual' => LeaveType::ACCRUAL_YEARLY]);

        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        $this->assertSame(0, LeaveAccrual::count(), 'Nothing accrues until a type asks for it.');
    }

    public function test_the_command_credits_and_says_what_it_did(): void
    {
        $this->monthlyType();
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->artisan('hrms:accrue-leave')
            ->expectsOutputToContain('Credited 8 months')
            ->assertSuccessful();
    }

    public function test_the_employee_can_see_what_was_credited(): void
    {
        $this->monthlyType();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->accruals->accrue();

        $this->actingAs($employee->user)
            ->get(route('leave.balance'))
            ->assertOk()
            ->assertSee('Monthly leave credited in 2026')
            ->assertSee('August 2026');
    }
}
