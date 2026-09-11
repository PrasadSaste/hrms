<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Mail\TemplatedPayslipMail;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\PayslipPdfService;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payroll;

    protected AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
        $this->attendance = app(AttendanceService::class);
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An employee on a 12 lakh CTC with the default component set. */
    protected function employeeWithSalary(float $ctc = 1200000, array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->payroll->applyDefaultComponents($structure);

        return $employee->fresh();
    }

    /** Mark the employee present on every working day of the given month. */
    protected function markFullAttendance(Employee $employee, int $year, int $month): void
    {
        $cursor = Carbon::create($year, $month, 1);
        $end = $cursor->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                $this->attendance->record($employee, [
                    'date' => $cursor->toDateString(),
                    'check_in' => '09:30',
                    'check_out' => '18:30',
                ]);
            }
            $cursor->addDay();
        }
    }

    public function test_a_payroll_run_can_be_created(): void
    {
        $this->seedReferenceData();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026], $accountant->user);

        $this->assertSame(PayrollStatus::Draft, $run->status);
        $this->assertSame('2026-06-01', $run->period_start->toDateString());
        $this->assertSame('2026-06-30', $run->period_end->toDateString());
        $this->assertStringStartsWith('PR-', $run->reference);
    }

    public function test_two_runs_cannot_cover_the_same_branch_and_period(): void
    {
        $this->seedReferenceData();
        $this->payroll->createRun(['month' => 6, 'year' => 2026]);

        $this->expectException(ValidationException::class);
        $this->payroll->createRun(['month' => 6, 'year' => 2026]);
    }

    public function test_generating_creates_a_payslip_per_eligible_employee(): void
    {
        $a = $this->employeeWithSalary();
        $b = $this->employeeWithSalary();

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $result = $this->payroll->generate($run);

        $this->assertSame(2, $result['generated']);
        $this->assertEmpty($result['skipped']);
        $this->assertSame(2, $run->fresh()->payslips()->count());
    }

    public function test_an_employee_without_a_salary_structure_is_skipped_by_name(): void
    {
        $this->employeeWithSalary();
        $noStructure = $this->makeEmployee(Roles::EMPLOYEE);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $result = $this->payroll->generate($run);

        $this->assertSame(1, $result['generated']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString($noStructure->employee_code, $result['skipped'][0]);
    }

    public function test_component_amounts_follow_the_configured_formula(): void
    {
        // 12 lakh CTC is 100,000 a month; basic is 40% of that.
        $employee = $this->employeeWithSalary(1200000);
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();
        $items = $payslip->items->keyBy('code');

        $this->assertSame(40000.0, $items['BASIC']->amount, 'Basic is 40% of monthly CTC.');
        $this->assertSame(20000.0, $items['HRA']->amount, 'HRA is 50% of basic.');
        $this->assertSame(1600.0, $items['CONV']->amount, 'Conveyance is a fixed amount.');
        // Not 12% of the whole 40,000 basic: provident fund is taken on a wage
        // capped at the statutory 15,000, so everybody above the cap pays 1,800.
        $this->assertSame(1800.0, $items['PF']->amount, 'Provident fund is 12% of a capped wage.');
        $this->assertSame(200.0, $items['PT']->amount, 'Professional tax comes from its slab table.');
    }

    public function test_gross_minus_deductions_equals_net_pay(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(
            round($payslip->gross_earnings - $payslip->total_deductions, 2),
            $payslip->net_pay,
        );
        $this->assertGreaterThan(0, $payslip->net_pay);
    }

    public function test_a_full_attendance_month_carries_no_loss_of_pay(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(22.0, $payslip->working_days);
        $this->assertSame(22.0, $payslip->paid_days);
        $this->assertSame(0.0, $payslip->lop_days);
    }

    public function test_absence_reduces_pay_proportionally(): void
    {
        $full = $this->employeeWithSalary(1200000);
        $partial = $this->employeeWithSalary(1200000);

        $this->markFullAttendance($full, 2026, 6);

        // Present on all but two working days.
        $cursor = Carbon::create(2026, 6, 1);
        $end = $cursor->copy()->endOfMonth();
        $skipped = 0;
        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                if ($skipped < 2) {
                    $skipped++;
                } else {
                    $this->attendance->record($partial, [
                        'date' => $cursor->toDateString(),
                        'check_in' => '09:30',
                        'check_out' => '18:30',
                    ]);
                }
            }
            $cursor->addDay();
        }

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $fullSlip = Payslip::where('employee_id', $full->id)->firstOrFail();
        $partialSlip = Payslip::where('employee_id', $partial->id)->firstOrFail();

        $this->assertSame(2.0, $partialSlip->lop_days);
        $this->assertSame(20.0, $partialSlip->paid_days);
        $this->assertLessThan($fullSlip->net_pay, $partialSlip->net_pay);

        // Basic is prorated by 20/22 of the full month.
        $expectedBasic = round(40000 * 20 / 22, 2);
        $this->assertEqualsWithDelta($expectedBasic, $partialSlip->basic_salary, 0.05);
    }

    public function test_a_flat_component_is_not_prorated_when_configured_that_way(): void
    {
        // Big enough that a single paid day still clears the professional tax
        // threshold, so what is being tested is proration rather than which
        // slab the month lands in.
        $employee = $this->employeeWithSalary(24000000);

        $this->assertFalse(SalaryComponent::where('code', 'PT')->firstOrFail()->prorate_on_lop);

        // Record only one working day, so the month is almost entirely unpaid.
        $this->attendance->record($employee, [
            'date' => '2026-06-01',
            'check_in' => '09:30',
            'check_out' => '18:30',
        ]);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        // Whole, not scaled by the pay factor.
        $this->assertSame(200.0, $payslip->items->firstWhere('code', 'PT')->amount);
        $this->assertLessThan(800000.0, $payslip->items->firstWhere('code', 'BASIC')->amount);
    }

    public function test_a_month_mostly_unpaid_falls_into_the_lower_tax_band(): void
    {
        // The deductions are worked out on the wages actually earned, so
        // somebody who earned almost nothing this month is below the threshold
        // rather than being charged as though they had worked it.
        $employee = $this->employeeWithSalary(1200000);

        $this->attendance->record($employee, [
            'date' => '2026-06-01',
            'check_in' => '09:30',
            'check_out' => '18:30',
        ]);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(0.0, $payslip->items->firstWhere('code', 'PT')->amount);
    }

    public function test_approving_a_run_publishes_its_payslips(): void
    {
        $this->employeeWithSalary();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $this->assertSame('draft', Payslip::first()->status);

        $this->payroll->approve($run->fresh(), $admin->user);

        $this->assertSame(PayrollStatus::Approved, $run->fresh()->status);
        $this->assertSame('published', Payslip::first()->fresh()->status);
    }

    public function test_marking_a_run_paid_settles_every_payslip(): void
    {
        $this->employeeWithSalary();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);
        $this->payroll->approve($run->fresh(), $admin->user);
        $this->payroll->markPaid($run->fresh(), 'NEFT-2026-06');

        $payslip = Payslip::first()->fresh();

        $this->assertSame(PayrollStatus::Paid, $run->fresh()->status);
        $this->assertSame('paid', $payslip->payment_status);
        $this->assertSame('NEFT-2026-06', $payslip->payment_reference);
    }

    public function test_an_unapproved_run_cannot_be_marked_paid(): void
    {
        $this->employeeWithSalary();
        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $this->expectException(ValidationException::class);
        $this->payroll->markPaid($run->fresh());
    }

    public function test_run_totals_match_the_sum_of_its_payslips(): void
    {
        $this->employeeWithSalary();
        $this->employeeWithSalary(1800000);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);
        $run->refresh();

        $this->assertSame(2, $run->total_employees);
        $this->assertEqualsWithDelta((float) Payslip::sum('gross_earnings'), $run->total_gross, 0.01);
        $this->assertEqualsWithDelta((float) Payslip::sum('net_pay'), $run->total_net, 0.01);
    }

    public function test_an_employee_only_sees_a_published_payslip(): void
    {
        $employee = $this->employeeWithSalary();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($employee->user)
            ->get(route('payslips.show', $payslip))
            ->assertForbidden();

        $this->payroll->approve($run->fresh(), $admin->user);

        $this->actingAs($employee->user)
            ->get(route('payslips.show', $payslip))
            ->assertOk();
    }

    public function test_an_employee_cannot_open_someone_elses_payslip(): void
    {
        $employee = $this->employeeWithSalary();
        $colleague = $this->employeeWithSalary();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);
        $this->payroll->approve($run->fresh(), $admin->user);

        $theirs = Payslip::where('employee_id', $colleague->id)->firstOrFail();

        $this->actingAs($employee->user)
            ->get(route('payslips.show', $theirs))
            ->assertForbidden();
    }

    public function test_a_payslip_renders_as_a_pdf(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();
        $bytes = app(PayslipPdfService::class)->bytes($payslip);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_emailing_a_payslip_queues_it_for_delivery(): void
    {
        Mail::fake();
        $employee = $this->employeeWithSalary();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);
        $this->payroll->approve($run->fresh(), $admin->user);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($admin->user)
            ->post(route('payslips.email', $payslip))
            ->assertRedirect();

        Mail::assertQueued(
            TemplatedPayslipMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::PAYSLIP_PUBLISHED,
        );

        // Queueing is not delivery: emailed_at is stamped by the listener once
        // the message actually leaves. EmailTest covers that side.
        $this->assertNull($payslip->fresh()->emailed_at);
    }

    public function test_net_pay_is_written_out_in_words(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertStringContainsString('Rupees', $payslip->net_pay_words);
        $this->assertStringEndsWith('Only', $payslip->net_pay_words);
    }

    public function test_a_joiner_is_only_paid_from_their_start_date(): void
    {
        // Joins on 15 June, so only the second half of the month is payable.
        $employee = $this->employeeWithSalary(1200000, ['date_of_joining' => Carbon::parse('2026-06-15')]);
        $this->markFullAttendance($employee, 2026, 6);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        // The month has 22 working days; only 12 fall on or after 15 June.
        $this->assertSame(22.0, $payslip->working_days);
        $this->assertSame(12.0, $payslip->paid_days);
        $this->assertSame(0.0, $payslip->lop_days, 'Days before joining are not loss of pay.');

        // Basic is 12/22 of a full month rather than the whole 40,000.
        $this->assertEqualsWithDelta(round(40000 * 12 / 22, 2), $payslip->basic_salary, 0.05);
    }

    public function test_an_exited_employee_is_left_out_of_later_runs(): void
    {
        $employee = $this->employeeWithSalary();
        $employee->update([
            'date_of_exit' => '2026-05-31',
            'employment_status' => 'resigned',
            'status' => 'inactive',
        ]);

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $result = $this->payroll->generate($run);

        $this->assertSame(0, $result['generated']);
    }

    public function test_paid_leave_is_paid_and_unpaid_leave_is_not(): void
    {
        $employee = $this->employeeWithSalary();
        $manager = $this->makeEmployee(Roles::HR_MANAGER, ['branch' => $employee->branch]);
        $leaveService = app(LeaveService::class);

        $this->markFullAttendance($employee, 2026, 6);

        // Replace two working days of attendance with approved paid leave.
        Attendance::where('employee_id', $employee->id)
            ->whereIn('date', ['2026-06-15', '2026-06-16'])
            ->delete();

        // Apply while the dates are still ahead, so the notice rule is satisfied.
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

        $request = $leaveService->apply($employee, [
            'leave_type_id' => LeaveType::where('code', 'CL')->firstOrFail()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => 'full_day',
            'reason' => 'Approved paid leave.',
        ]);
        $leaveService->approve($request, $manager->user);

        // Back to July, so June is a completed month for payroll.
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));

        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(2.0, $payslip->paid_leave_days);
        $this->assertSame(0.0, $payslip->lop_days, 'Paid leave must not reduce pay.');
        $this->assertSame(22.0, $payslip->paid_days);
    }
}
