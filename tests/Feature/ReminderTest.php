<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Mail\TemplatedMail;
use App\Models\AttendanceRegularization;
use App\Models\AutomationRun;
use App\Models\BackgroundCheck;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ReminderService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Chasing what is sitting still, and saying whether a month is fit to be paid.
 */
class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-09 09:00:00'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A leave request applied for on a given day and never decided. */
    protected function pendingLeave($employee, string $appliedOn): LeaveRequest
    {
        $type = LeaveType::active()->first();

        return LeaveRequest::create([
            'reference' => 'LR-'.str()->random(8),
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-21',
            'total_days' => 2,
            'reason' => 'Family function',
            'status' => LeaveStatus::Pending,
            'applied_on' => $appliedOn,
        ]);
    }

    // ------------------------------------------------------------- chasing

    public function test_a_request_left_sitting_is_chased_to_its_approver(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $this->pendingLeave($employee, '2026-09-01');

        Artisan::call('hrms:chase-pending');

        Mail::assertQueued(TemplatedMail::class);
        $this->assertGreaterThan(0, AutomationRun::for('reminders.chase-pending')->latest('id')->first()->affected);
    }

    public function test_something_raised_this_morning_is_not_chased_this_morning(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $this->pendingLeave($employee, '2026-09-09');

        Artisan::call('hrms:chase-pending');

        Mail::assertNothingQueued();
    }

    public function test_a_decided_request_is_left_alone(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $this->pendingLeave($employee, '2026-09-01')->update(['status' => LeaveStatus::Approved]);

        Artisan::call('hrms:chase-pending');

        Mail::assertNothingQueued();
    }

    public function test_one_person_with_several_things_waiting_gets_one_message(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $this->pendingLeave($employee, '2026-09-01');
        $this->pendingLeave($employee, '2026-09-02');

        AttendanceRegularization::create([
            'employee_id' => $employee->id,
            'date' => '2026-09-01',
            'reason' => 'Forgot to punch out',
            'status' => LeaveStatus::Pending,
            'created_at' => Carbon::parse('2026-09-01'),
            'updated_at' => Carbon::parse('2026-09-01'),
        ]);

        Artisan::call('hrms:chase-pending');

        // Three things, one manager, one email.
        Mail::assertQueuedCount(1);
    }

    public function test_a_joiner_who_never_sent_their_documents_is_chased(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        BackgroundCheck::create([
            'employee_id' => $employee->id,
            'status' => BackgroundCheck::INVITED,
            'invited_at' => Carbon::parse('2026-09-01'),
        ]);

        Artisan::call('hrms:chase-pending');

        Mail::assertQueued(TemplatedMail::class);
    }

    public function test_a_check_already_submitted_is_not_chased(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        BackgroundCheck::create([
            'employee_id' => $employee->id,
            'status' => BackgroundCheck::SUBMITTED,
            'invited_at' => Carbon::parse('2026-09-01'),
            'submitted_at' => Carbon::parse('2026-09-03'),
        ]);

        Artisan::call('hrms:chase-pending');

        Mail::assertNothingQueued();
    }

    public function test_a_reminder_is_never_sent_nowhere(): void
    {
        // Nobody manages this person, so HR hears about it instead.
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->pendingLeave($employee, '2026-09-01');

        $approver = app(ReminderService::class)->approverFor($employee->fresh());

        $this->assertNotNull($approver);
        $this->assertTrue($approver->hasRole(Roles::HR_MANAGER) || $approver->hasRole(Roles::SUPER_ADMIN));
    }

    public function test_nobody_is_asked_to_approve_their_own_request(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);

        // A manager who reports to themselves would otherwise be chased about
        // their own leave.
        $manager->update(['reporting_to' => $manager->id]);

        $approver = app(ReminderService::class)->approverFor($manager->fresh());

        $this->assertFalse($approver?->is($manager->user));
    }

    public function test_a_dry_run_chases_nobody(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);
        $this->pendingLeave($employee, '2026-09-01');

        Artisan::call('hrms:chase-pending', ['--dry-run' => true]);

        Mail::assertNothingQueued();
        $this->assertSame(0, AutomationRun::for('reminders.chase-pending')->latest('id')->first()->affected);
    }

    public function test_how_long_something_may_sit_can_be_changed(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $this->pendingLeave($employee, '2026-09-07');

        // Two days old: not chased at three, chased at one.
        Artisan::call('hrms:chase-pending');
        Mail::assertNothingQueued();

        Artisan::call('hrms:chase-pending', ['--after' => 1]);
        Mail::assertQueued(TemplatedMail::class);
    }

    // --------------------------------------------------- payroll readiness

    public function test_a_month_with_nothing_outstanding_is_reported_clean(): void
    {
        $this->makeEmployee(Roles::HR_MANAGER);

        Artisan::call('hrms:payroll-readiness', ['--month' => '2026-08']);

        $this->assertStringContainsString(
            'Nothing needs attention',
            AutomationRun::for('payroll.readiness')->latest('id')->first()->summary,
        );
    }

    public function test_leave_still_undecided_in_the_month_is_reported(): void
    {
        $this->makeEmployee(Roles::HR_MANAGER);
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        LeaveRequest::create([
            'reference' => 'LR-'.str()->random(8),
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::active()->first()->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'total_days' => 2,
            'reason' => 'Unwell',
            'status' => LeaveStatus::Pending,
            'applied_on' => '2026-08-05',
        ]);

        Artisan::call('hrms:payroll-readiness', ['--month' => '2026-08']);

        $summary = AutomationRun::for('payroll.readiness')->latest('id')->first()->summary;

        $this->assertStringContainsString('1 leave request still undecided', $summary);
    }

    public function test_the_readiness_check_changes_nothing(): void
    {
        $this->makeEmployee(Roles::HR_MANAGER);
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['reporting_to' => $manager->id]);

        $request = LeaveRequest::create([
            'reference' => 'LR-'.str()->random(8),
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::active()->first()->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'total_days' => 2,
            'reason' => 'Unwell',
            'status' => LeaveStatus::Pending,
            'applied_on' => '2026-08-05',
        ]);

        Artisan::call('hrms:payroll-readiness', ['--month' => '2026-08']);

        // It reports; it does not decide.
        $this->assertSame(LeaveStatus::Pending, $request->fresh()->status);
    }
}
