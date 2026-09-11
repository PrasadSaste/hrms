<?php

namespace Tests\Feature;

use App\Enums\DayType;
use App\Enums\LeaveStatus;
use App\Mail\TemplatedMail;
use App\Models\Holiday;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    protected LeaveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaveService::class);
        // A Wednesday, so the weekday arithmetic below is predictable.
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function casualLeave(): LeaveType
    {
        $this->seedReferenceData();

        return LeaveType::where('code', 'CL')->firstOrFail();
    }

    public function test_leave_days_exclude_weekends(): void
    {
        $employee = $this->makeEmployee();

        // Friday 12 June to Monday 15 June is four calendar days, two working.
        $days = $this->service->calculateDays(
            $employee,
            Carbon::parse('2026-06-12'),
            Carbon::parse('2026-06-15'),
            DayType::FullDay,
        );

        $this->assertSame(2.0, $days);
    }

    public function test_leave_days_exclude_public_holidays(): void
    {
        $employee = $this->makeEmployee();

        Holiday::create([
            'name' => 'Founders Day',
            'date' => '2026-06-17',
            'type' => 'public',
        ]);

        // Monday 15 to Friday 19 is five weekdays, less one holiday.
        $days = $this->service->calculateDays(
            $employee,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-19'),
            DayType::FullDay,
        );

        $this->assertSame(4.0, $days);
    }

    public function test_a_half_day_request_costs_half_a_day(): void
    {
        $employee = $this->makeEmployee();

        $days = $this->service->calculateDays(
            $employee,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-15'),
            DayType::FirstHalf,
        );

        $this->assertSame(0.5, $days);
    }

    public function test_an_employee_can_apply_for_leave(): void
    {
        Mail::fake();
        $employee = $this->makeEmployee();
        $type = $this->casualLeave();

        $this->actingAs($employee->user)->post(route('leave.store'), [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ])->assertRedirect();

        $request = LeaveRequest::first();

        $this->assertNotNull($request);
        $this->assertSame(2.0, $request->total_days);
        $this->assertSame(LeaveStatus::Pending, $request->status);
        $this->assertStringStartsWith('LV-', $request->reference);
    }

    public function test_applying_notifies_the_approver_by_email(): void
    {
        Mail::fake();
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'branch' => $manager->branch,
            'reporting_to' => $manager->id,
        ]);

        $this->actingAs($employee->user)->post(route('leave.store'), [
            'leave_type_id' => $this->casualLeave()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::LEAVE_SUBMITTED,
        );
    }

    public function test_approving_leave_deducts_the_balance_and_emails_the_employee(): void
    {
        Mail::fake();
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);
        $type = $this->casualLeave();

        $request = $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        $this->actingAs($manager->user)
            ->post(route('leave.approve', $request), ['approver_remarks' => 'Approved.'])
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);

        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)->first();

        $this->assertSame(2.0, $allocation->used_days);
        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::LEAVE_APPROVED,
        );
    }

    public function test_rejecting_leave_leaves_the_balance_untouched(): void
    {
        Mail::fake();
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);
        $type = $this->casualLeave();

        $request = $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        $this->actingAs($manager->user)
            ->post(route('leave.reject', $request), ['approver_remarks' => 'Release week, please re-apply.'])
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Rejected, $request->fresh()->status);

        // Nothing was consumed, so the full entitlement is still available.
        $this->assertSame(
            $this->service->proratedEntitlement($employee, $type, 2026),
            $this->service->remainingDays($employee, $type, 2026),
        );
        $this->assertSame(0.0, (float) LeaveAllocation::sum('used_days'));
    }

    public function test_cancelling_an_approved_request_returns_the_balance(): void
    {
        Mail::fake();
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);
        $type = $this->casualLeave();

        $request = $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        $this->service->approve($request, $manager->user);
        $this->assertSame(2.0, LeaveAllocation::first()->used_days);

        $this->service->cancel($request->fresh(), 'Plans changed.');

        $this->assertSame(0.0, LeaveAllocation::first()->fresh()->used_days);
        $this->assertSame(LeaveStatus::Cancelled, $request->fresh()->status);
    }

    public function test_an_approver_cannot_action_their_own_request(): void
    {
        $manager = $this->makeEmployee(Roles::HR_MANAGER);

        $request = $this->service->apply($manager, [
            'leave_type_id' => $this->casualLeave()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        $this->actingAs($manager->user)
            ->post(route('leave.approve', $request))
            ->assertForbidden();
    }

    public function test_overlapping_requests_are_rejected(): void
    {
        $employee = $this->makeEmployee();
        $type = $this->casualLeave();

        $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Family commitment out of town.',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-18',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Another commitment.',
        ]);
    }

    public function test_a_request_beyond_the_balance_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $type = $this->casualLeave();

        // Casual leave allows 12 days a year; ask for far more.
        $this->expectException(ValidationException::class);

        $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-08-15',
            'day_type' => DayType::FullDay->value,
            'reason' => 'An unreasonably long break.',
        ]);
    }

    public function test_the_consecutive_day_cap_is_enforced(): void
    {
        $employee = $this->makeEmployee();
        $type = $this->casualLeave();
        // Casual leave caps at three consecutive days by default.
        $this->assertSame(3, $type->max_consecutive_days);

        $this->expectException(ValidationException::class);

        $this->service->apply($employee, [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'day_type' => DayType::FullDay->value,
            'reason' => 'A five-day break.',
        ]);
    }

    public function test_the_notice_period_is_enforced(): void
    {
        $employee = $this->makeEmployee();
        $earned = LeaveType::where('code', 'EL')->firstOrFail();
        // Earned leave requires seven days notice.
        $this->assertSame(7, $earned->min_notice_days);

        $this->expectException(ValidationException::class);

        $this->service->apply($employee, [
            'leave_type_id' => $earned->id,
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDay()->toDateString(),
            'day_type' => DayType::FullDay->value,
            'reason' => 'Short notice trip.',
        ]);
    }

    public function test_gender_specific_leave_is_not_offered_to_everyone(): void
    {
        $this->seedReferenceData();
        $male = $this->makeEmployee(Roles::EMPLOYEE, ['gender' => 'male']);
        $maternity = LeaveType::where('code', 'ML')->firstOrFail();

        $this->assertFalse($maternity->isApplicableTo($male));

        $this->expectException(ValidationException::class);

        $this->service->apply($male, [
            'leave_type_id' => $maternity->id,
            'start_date' => Carbon::today()->addMonths(2)->toDateString(),
            'end_date' => Carbon::today()->addMonths(2)->addDays(5)->toDateString(),
            'day_type' => DayType::FullDay->value,
            'reason' => 'Not applicable.',
        ]);
    }

    public function test_entitlement_is_prorated_for_a_mid_year_joiner(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2026-07-01'),
        ]);
        $type = $this->casualLeave();

        // Joining in July leaves six of twelve months, so half of 12 days.
        $this->assertSame(6.0, $this->service->proratedEntitlement($employee, $type, 2026));
    }

    public function test_a_full_year_employee_gets_the_whole_entitlement(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ]);

        $this->assertSame(12.0, $this->service->proratedEntitlement($employee, $this->casualLeave(), 2026));
    }

    public function test_bulk_allocation_creates_rows_for_every_employee(): void
    {
        $this->seedReferenceData();
        $this->makeEmployee();
        $this->makeEmployee();

        $created = $this->service->allocateYear(2026);

        $this->assertGreaterThan(0, $created);
        $this->assertSame($created, LeaveAllocation::where('year', 2026)->count());
    }

    public function test_carry_forward_is_capped_by_the_leave_type(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ]);
        $earned = LeaveType::where('code', 'EL')->firstOrFail();

        // Carry-forward on a type granted for the year: a monthly type's
        // allocation is a running total of what has been credited, and is
        // covered in LeaveAccrualTest instead.
        $earned->update(['accrual' => LeaveType::ACCRUAL_YEARLY]);

        // Leave 18 unused days in the prior year against a 30-day cap.
        LeaveAllocation::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $earned->id,
            'year' => 2025,
            'allocated_days' => 18,
            'used_days' => 0,
        ]);

        $this->service->allocateYear(2026);

        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $earned->id)
            ->where('year', 2026)
            ->first();

        $this->assertSame(18.0, $allocation->carried_forward_days);
        $this->assertSame(36.0, $allocation->entitledDays());
    }

    public function test_an_employee_cannot_view_another_employees_request(): void
    {
        $employee = $this->makeEmployee();
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $employee->branch]);

        $request = $this->service->apply($colleague, [
            'leave_type_id' => $this->casualLeave()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => DayType::FullDay->value,
            'reason' => 'Private matter.',
        ]);

        $this->actingAs($employee->user)
            ->get(route('leave.show', $request))
            ->assertForbidden();
    }
}
