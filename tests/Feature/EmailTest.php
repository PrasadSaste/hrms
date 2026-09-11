<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Mail\TemplatedPayslipMail;
use App\Models\Announcement;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Notifications\ResetPasswordNotification;
use App\Services\LeaveService;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\NotificationTemplateRenderer;
use App\Services\PayrollService;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A Wednesday, so the leave dates below land on working days.
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------- rendering

    public function test_every_mailable_renders_without_error(): void
    {
        $this->seedReferenceData();
        $renderer = app(NotificationTemplateRenderer::class);

        foreach (NotificationEvents::keys() as $key) {
            if (! NotificationEvents::supports($key, 'mail')) {
                continue;
            }

            $mailable = new TemplatedMail($key, $renderer->sampleData($key));
            $html = $mailable->render();

            $this->assertNotEmpty($mailable->envelope()->subject, $key.' has no subject');
            $this->assertStringContainsString('<html', strtolower($html));
            // Markdown must be rendered, not passed through raw.
            $this->assertStringNotContainsString('**', $html);
            // Every placeholder must have been substituted.
            $this->assertStringNotContainsString('{{', $html, $key.' left a placeholder unfilled');
        }
    }

    public function test_the_company_name_comes_from_settings(): void
    {
        $this->seedReferenceData();
        Setting::put('company_name', 'Beyond Sure Insurance', 'company');

        $mailable = new TemplatedMail(
            NotificationEvents::ACCOUNT_CREDENTIALS,
            app(NotificationTemplateRenderer::class)->sampleData(NotificationEvents::ACCOUNT_CREDENTIALS),
        );

        $this->assertStringContainsString('Beyond Sure Insurance', $mailable->envelope()->subject);
    }

    // -------------------------------------------------------------- triggers

    public function test_creating_an_employee_sends_a_welcome_email(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Nadia', 'last_name' => 'Rahman',
            'email' => 'nadia.rahman@example.test',
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time', 'employment_status' => 'probation',
            'date_of_joining' => now()->toDateString(), 'notice_period_days' => 30,
            'status' => 'active', 'create_account' => '1',
            'role' => Roles::EMPLOYEE, 'send_welcome_email' => '1',
        ]);

        Mail::assertQueued(
            TemplatedMail::class,
            fn ($mail) => $mail->eventKey === NotificationEvents::EMPLOYEE_WELCOME
                && $mail->hasTo('nadia.rahman@example.test'),
        );
    }

    public function test_the_welcome_email_can_be_switched_off_per_employee(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Quiet', 'last_name' => 'Start',
            'email' => 'quiet.start@example.test',
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time', 'employment_status' => 'probation',
            'date_of_joining' => now()->toDateString(), 'notice_period_days' => 30,
            'status' => 'active', 'create_account' => '1',
            'role' => Roles::EMPLOYEE, 'send_welcome_email' => '0',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_leave_reaches_both_the_manager_and_the_branch_manager(): void
    {
        Mail::fake();

        $branchHead = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $branch = $branchHead->branch;
        $branch->update(['manager_id' => $branchHead->id]);

        $lineManager = $this->makeEmployee(Roles::HR_MANAGER, ['branch' => $branch]);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'branch' => $branch,
            'reporting_to' => $lineManager->id,
        ]);

        $request = app(LeaveService::class)->apply($employee->fresh(), [
            'leave_type_id' => LeaveType::where('code', 'CL')->value('id'),
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => 'full_day',
            'reason' => 'Checking both approvers are told.',
        ]);

        app(NotificationService::class)->notifyLeaveSubmitted($request);

        $submitted = fn ($email) => fn ($m) => $m->eventKey === NotificationEvents::LEAVE_SUBMITTED
            && $m->hasTo($email);

        Mail::assertQueued(TemplatedMail::class, $submitted($lineManager->email));
        Mail::assertQueued(TemplatedMail::class, $submitted($branchHead->email));
    }

    public function test_an_approval_and_a_rejection_both_reach_the_employee(): void
    {
        Mail::fake();
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);
        $service = app(LeaveService::class);
        $typeId = LeaveType::where('code', 'CL')->value('id');

        $approved = $service->apply($employee, [
            'leave_type_id' => $typeId, 'start_date' => '2026-06-15', 'end_date' => '2026-06-15',
            'day_type' => 'full_day', 'reason' => 'First request.',
        ]);
        app(NotificationService::class)->notifyLeaveActioned(
            $service->approve($approved, $manager->user)
        );

        $rejected = $service->apply($employee, [
            'leave_type_id' => $typeId, 'start_date' => '2026-06-22', 'end_date' => '2026-06-22',
            'day_type' => 'full_day', 'reason' => 'Second request.',
        ]);
        app(NotificationService::class)->notifyLeaveActioned(
            $service->reject($rejected, $manager->user, 'Not this week.')
        );

        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::LEAVE_APPROVED && $m->hasTo($employee->email),
        );
        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::LEAVE_REJECTED && $m->hasTo($employee->email),
        );
    }

    public function test_an_announcement_only_reaches_its_branch(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $target = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $elsewhere = $this->makeEmployee(Roles::EMPLOYEE);

        $announcement = Announcement::create([
            'title' => 'Branch notice', 'body' => 'For one office only.',
            'branch_id' => $hr->branch_id, 'status' => 'published',
            'published_at' => now(), 'notify_by_email' => true,
            'created_by' => $hr->user->id,
        ]);

        app(NotificationService::class)->broadcastAnnouncement($announcement);

        $announced = fn ($email) => fn ($m) => $m->eventKey === NotificationEvents::ANNOUNCEMENT_PUBLISHED
            && $m->hasTo($email);

        Mail::assertQueued(TemplatedMail::class, $announced($target->email));
        Mail::assertNotQueued(TemplatedMail::class, $announced($elsewhere->email));
    }

    public function test_a_password_reset_link_is_sent(): void
    {
        Notification::fake();
        $employee = $this->makeEmployee();

        $this->post(route('password.email'), ['email' => $employee->email]);

        Notification::assertSentTo($employee->user, ResetPasswordNotification::class);
    }

    // -------------------------------------------------------------- payslips

    public function test_the_payslip_email_carries_the_pdf(): void
    {
        $payslip = $this->publishedPayslip();

        $mailable = new TemplatedPayslipMail($payslip, NotificationEvents::PAYSLIP_PUBLISHED);
        $mailable->render();
        $attachments = $mailable->attachments();

        $this->assertCount(1, $attachments);
        $this->assertStringEndsWith('.pdf', $attachments[0]->as ?? '');
    }

    public function test_the_payslip_email_is_tagged_with_its_payslip(): void
    {
        $payslip = $this->publishedPayslip();

        $headers = (new TemplatedPayslipMail($payslip, NotificationEvents::PAYSLIP_PUBLISHED))->headers();

        $this->assertSame((string) $payslip->id, $headers->text['X-HRMS-Payslip']);
    }

    public function test_queueing_a_payslip_does_not_claim_it_was_delivered(): void
    {
        Mail::fake();
        $payslip = $this->publishedPayslip();

        app(NotificationService::class)->sendPayslip($payslip);

        Mail::assertQueued(
            TemplatedPayslipMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::PAYSLIP_PUBLISHED,
        );
        $this->assertNull(
            $payslip->fresh()->emailed_at,
            'emailed_at must stay empty until the message actually leaves.',
        );
    }

    public function test_emailed_at_is_stamped_once_the_message_is_sent(): void
    {
        // No Mail::fake here: the array transport really sends, which fires the
        // MessageSent event that stamps the payslip.
        $payslip = $this->publishedPayslip();
        $this->assertNull($payslip->emailed_at);

        app(NotificationService::class)->sendPayslip($payslip);

        $this->assertNotNull($payslip->fresh()->emailed_at);
    }

    // ---------------------------------------------------------- master switch

    public function test_turning_notifications_off_stops_every_email(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('email_notifications_enabled', false, 'mail');

        $employee = $this->makeEmployee();
        $sent = app(NotificationService::class)->sendWelcome($employee, 'Temp12345!');

        $this->assertFalse($sent);
        Mail::assertNothingQueued();
    }

    public function test_mail_is_skipped_when_there_is_no_address(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        $employee = $this->makeEmployee();

        $this->assertFalse(
            app(NotificationDispatcher::class)->toAddress(NotificationEvents::EMPLOYEE_WELCOME, null, [])
        );

        Mail::assertNothingQueued();
    }

    /** A published payslip belonging to a fresh employee. */
    protected function publishedPayslip(): Payslip
    {
        $employee = $this->makeEmployee();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $payrollService = app(PayrollService::class);

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);
        $payrollService->applyDefaultComponents($structure);

        $run = $payrollService->createRun(['month' => 5, 'year' => 2026]);
        $payrollService->generate($run);
        $payrollService->approve($run->fresh(), $admin->user);

        return Payslip::where('employee_id', $employee->id)->firstOrFail();
    }

    // ---------------------------------------------------------- queue worker

    public function test_each_queued_job_starts_with_a_fresh_smtp_mailer(): void
    {
        // The SMTP transport is only built here, never connected: nothing
        // talks to a server until a message is actually sent.
        config(['mail.default' => 'smtp']);
        $manager = app('mail.manager');
        $before = $manager->mailer();

        event(new JobProcessing('database', $this->createMock(Job::class)));

        $this->assertNotSame($before, $manager->mailer(), 'An SMTP mailer should be rebuilt for every job so an idle connection is never reused.');
    }

    public function test_in_memory_mailers_survive_between_queued_jobs(): void
    {
        config(['mail.default' => 'array']);
        $manager = app('mail.manager');
        $before = $manager->mailer();

        event(new JobProcessing('sync', $this->createMock(Job::class)));

        $this->assertSame($before, $manager->mailer(), 'The array transport keeps sent messages in memory, so it must not be dropped.');
    }
}
