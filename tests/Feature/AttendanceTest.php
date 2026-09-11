<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Services\AttendanceService;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
        // A Wednesday, so the default Mon-Fri shift treats it as a working day.
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:25:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A position to punch from; these tests are about the maths, not the rule. */
    protected function somewhere(): PunchLocation
    {
        return new PunchLocation(12.9352, 77.6245, 15);
    }

    public function test_an_employee_can_check_in(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'), ['latitude' => 12.9352, 'longitude' => 77.6245])
            ->assertRedirect();

        $attendance = Attendance::where('employee_id', $employee->id)->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->check_in);
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(0, $attendance->late_minutes);
    }

    public function test_arriving_after_the_grace_period_is_marked_late(): void
    {
        $employee = $this->makeEmployee();
        // Shift starts 09:30 with 15 minutes grace, so 10:05 is 20 minutes late.
        Carbon::setTestNow(Carbon::parse('2026-06-10 10:05:00'));

        $attendance = $this->service->checkIn($employee, null, 'web', null, $this->somewhere());

        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(20, $attendance->late_minutes);
    }

    public function test_checking_in_twice_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $this->service->checkIn($employee, null, 'web', null, $this->somewhere());

        $this->expectException(ValidationException::class);
        $this->service->checkIn($employee, null, 'web', null, $this->somewhere());
    }

    public function test_check_out_computes_worked_and_overtime_minutes(): void
    {
        $employee = $this->makeEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-06-10 09:30:00'), 'web', null, $this->somewhere());
        $attendance = $this->service->checkOut($employee, Carbon::parse('2026-06-10 19:30:00'), 'web', null, $this->somewhere());

        // Ten hours elapsed, minus a 60-minute break, is nine hours worked.
        $this->assertSame(540, $attendance->worked_minutes);
        $this->assertSame(60, $attendance->overtime_minutes);
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
    }

    public function test_a_short_day_is_marked_as_a_half_day(): void
    {
        $employee = $this->makeEmployee();

        $this->service->checkIn($employee, Carbon::parse('2026-06-10 09:30:00'), 'web', null, $this->somewhere());
        $attendance = $this->service->checkOut($employee, Carbon::parse('2026-06-10 13:00:00'), 'web', null, $this->somewhere());

        // Three and a half hours minus the break falls under the four-hour threshold.
        $this->assertSame(AttendanceStatus::HalfDay, $attendance->status);
    }

    public function test_checking_out_without_checking_in_is_rejected(): void
    {
        $employee = $this->makeEmployee();

        $this->expectException(ValidationException::class);
        $this->service->checkOut($employee, null, 'web', null, $this->somewhere());
    }

    public function test_checking_out_before_checking_in_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $this->service->checkIn($employee, Carbon::parse('2026-06-10 09:30:00'), 'web', null, $this->somewhere());

        $this->expectException(ValidationException::class);
        $this->service->checkOut($employee, Carbon::parse('2026-06-10 08:00:00'), 'web', null, $this->somewhere());
    }

    public function test_hr_can_record_attendance_for_someone_else(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('attendance.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-06-09',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'status' => AttendanceStatus::Present->value,
            'remarks' => 'Biometric device was offline.',
        ])->assertRedirect();

        $attendance = Attendance::where('employee_id', $employee->id)->first();

        $this->assertNotNull($attendance);
        $this->assertSame(480, $attendance->worked_minutes);
        $this->assertSame($hr->user->id, $attendance->marked_by);
    }

    public function test_an_employee_cannot_record_attendance_for_others(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $employee->branch]);

        $this->actingAs($employee->user)->post(route('attendance.store'), [
            'employee_id' => $colleague->id,
            'date' => '2026-06-09',
            'status' => AttendanceStatus::Present->value,
        ])->assertForbidden();
    }

    public function test_only_one_attendance_row_exists_per_employee_per_day(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        foreach (['09:30', '10:00'] as $time) {
            $this->service->record($employee, [
                'date' => '2026-06-09',
                'check_in' => $time,
                'check_out' => '18:30',
            ], $hr->user->id);
        }

        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());
    }

    public function test_the_monthly_summary_counts_weekends_and_working_days(): void
    {
        $employee = $this->makeEmployee();

        $summary = $this->service->monthlySummary($employee, 2026, 6);
        $totals = $summary['totals'];

        // June 2026 has 30 days: 22 weekdays and 8 weekend days.
        $this->assertSame(30, count($summary['days']));
        $this->assertSame(22, $totals['working_days']);
        $this->assertSame(8, $totals['weekend_days']);
    }

    public function test_attendance_percentage_uses_elapsed_days_not_the_whole_month(): void
    {
        $employee = $this->makeEmployee();

        // Present on every working day up to and including today (10 June).
        for ($day = 1; $day <= 10; $day++) {
            $date = Carbon::create(2026, 6, $day);
            if ($date->isoWeekday() >= 6) {
                continue;
            }
            $this->service->record($employee, [
                'date' => $date->toDateString(),
                'check_in' => '09:30',
                'check_out' => '18:30',
            ]);
        }

        $totals = $this->service->monthlySummary($employee, 2026, 6)['totals'];

        $this->assertSame(22, $totals['working_days']);
        $this->assertSame(8, $totals['elapsed_working_days']);
        $this->assertSame(100.0, $totals['attendance_percentage']);
    }

    public function test_a_past_working_day_with_no_record_counts_as_absent(): void
    {
        $employee = $this->makeEmployee();

        $totals = $this->service->monthlySummary($employee, 2026, 6)['totals'];

        // Nothing recorded, so every elapsed working day is absent.
        $this->assertSame(8.0, $totals['absent_days']);
        $this->assertSame(0.0, $totals['present_days']);
    }

    public function test_an_employee_can_request_a_regularisation(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.regularizations.store'), [
            'date' => '2026-06-09',
            'requested_check_in' => '09:30',
            'requested_check_out' => '18:30',
            'reason' => 'I forgot to punch out before leaving for a client visit.',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_regularizations', [
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);
    }

    public function test_approving_a_regularisation_writes_the_attendance_record(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($employee->user)->post(route('attendance.regularizations.store'), [
            'date' => '2026-06-09',
            'requested_check_in' => '09:30',
            'requested_check_out' => '18:30',
            'reason' => 'I forgot to punch out before leaving for a client visit.',
        ]);

        $regularization = AttendanceRegularization::first();

        $this->actingAs($hr->user)
            ->post(route('attendance.regularizations.approve', $regularization), [
                'review_remarks' => 'Confirmed with the client.',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $regularization->fresh()->status->value);

        $attendance = Attendance::where('employee_id', $employee->id)->first();
        $this->assertNotNull($attendance);
        $this->assertSame(480, $attendance->worked_minutes);
    }
}
