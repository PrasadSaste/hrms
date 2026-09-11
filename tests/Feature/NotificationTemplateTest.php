<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\LeaveType;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Services\LeaveService;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\NotificationTemplateRenderer;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
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

    // ------------------------------------------------------------- catalogue

    public function test_every_event_has_content_for_the_channels_it_claims(): void
    {
        $this->seedReferenceData();

        foreach (NotificationEvents::all() as $key => $event) {
            $this->assertNotEmpty($event['channels'], $key.' declares no channel');

            if (in_array('mail', $event['channels'], true)) {
                $this->assertNotEmpty($event['email']['subject'] ?? null, $key.' has no subject');
                $this->assertNotEmpty($event['email']['body'] ?? null, $key.' has no body');
            }

            if (in_array('database', $event['channels'], true)) {
                $this->assertNotEmpty($event['database']['title'] ?? null, $key.' has no title');
                $this->assertNotEmpty($event['database']['message'] ?? null, $key.' has no message');
            }
        }
    }

    public function test_every_placeholder_used_in_a_template_is_declared(): void
    {
        foreach (NotificationEvents::all() as $key => $event) {
            $declared = array_keys(NotificationEvents::placeholdersFor($key));
            $text = json_encode($event['email'] ?? []).json_encode($event['database'] ?? []);

            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);

            foreach (array_unique($matches[1]) as $token) {
                $this->assertContains($token, $declared, $key.' uses an undeclared {{ '.$token.' }}');
            }
        }
    }

    // --------------------------------------------------------------- console

    public function test_an_administrator_can_open_the_console(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('notification-templates.index'))
            ->assertOk()
            ->assertSee('Leave approved')
            ->assertSee('Salary slip');
    }

    public function test_an_employee_cannot_reach_the_console(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)
            ->get(route('notification-templates.index'))
            ->assertForbidden();

        $this->actingAs($employee->user)
            ->put(route('notification-templates.update', NotificationEvents::LEAVE_APPROVED), [
                'subject' => 'Mine now',
            ])
            ->assertForbidden();
    }

    public function test_an_unknown_event_is_not_found(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('notification-templates.edit', 'nothing.here'))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- wording

    public function test_edited_wording_is_what_gets_sent(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('notification-templates.update', NotificationEvents::ACCOUNT_CREDENTIALS), [
                'subject' => 'Welcome aboard, {{ user_name }}',
                'body' => 'Your password is {{ temporary_password }}.',
                'mail_enabled' => '1',
            ])
            ->assertRedirect(route('notification-templates.index'));

        app(NotificationService::class)->sendCredentials($admin->user, 'Temp12345!');

        Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) use ($admin) {
            $rendered = app(NotificationTemplateRenderer::class)
                ->email($mail->eventKey, $mail->data, $mail->overrides);

            return $rendered['subject'] === 'Welcome aboard, '.$admin->user->name
                && str_contains($rendered['body'], 'Temp12345!');
        });
    }

    public function test_wording_left_at_the_default_is_not_stored_as_an_edit(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $default = NotificationEvents::find(NotificationEvents::LEAVE_APPROVED);

        $this->actingAs($admin->user)->put(
            route('notification-templates.update', NotificationEvents::LEAVE_APPROVED),
            [
                'subject' => $default['email']['subject'],
                'body' => $default['email']['body'],
                'mail_enabled' => '1',
                'database_enabled' => '1',
            ],
        );

        $this->assertNull(NotificationTemplate::where('key', NotificationEvents::LEAVE_APPROVED)->value('subject'));
        $this->assertFalse(NotificationTemplate::resolve(NotificationEvents::LEAVE_APPROVED)['customised']);
    }

    public function test_a_blank_field_falls_back_to_the_shipped_wording(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $key = NotificationEvents::LEAVE_REJECTED;

        $this->actingAs($admin->user)->put(route('notification-templates.update', $key), [
            'subject' => '',
            'mail_enabled' => '1',
        ]);

        $this->assertSame(
            NotificationEvents::find($key)['email']['subject'],
            NotificationTemplate::resolve($key)['subject'],
        );
    }

    public function test_the_original_wording_can_be_restored(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $key = NotificationEvents::LEAVE_APPROVED;

        $this->actingAs($admin->user)->put(route('notification-templates.update', $key), [
            'subject' => 'Something else entirely',
            'mail_enabled' => '1',
        ]);
        $this->assertTrue(NotificationTemplate::resolve($key)['customised']);

        $this->actingAs($admin->user)
            ->delete(route('notification-templates.reset', $key))
            ->assertRedirect(route('notification-templates.edit', $key));

        $this->assertFalse(NotificationTemplate::resolve($key)['customised']);
        $this->assertSame(
            NotificationEvents::find($key)['email']['subject'],
            NotificationTemplate::resolve($key)['subject'],
        );
    }

    // --------------------------------------------------------------- switches

    public function test_switching_email_off_stops_the_email_but_keeps_the_bell(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $admin->branch]);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.channels', NotificationEvents::LEAVE_APPROVED), [
                'channel' => 'mail',
                'enabled' => '0',
            ])
            ->assertRedirect();

        $leave = $this->applyAndApprove($employee, $admin);
        app(NotificationService::class)->notifyLeaveActioned($leave);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_switching_the_bell_off_stops_the_in_app_notification(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $admin->branch]);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.channels', NotificationEvents::LEAVE_APPROVED), [
                'channel' => 'database',
                'enabled' => '0',
            ]);

        $leave = $this->applyAndApprove($employee, $admin);
        app(NotificationService::class)->notifyLeaveActioned($leave);

        $this->assertDatabaseCount('notifications', 0);
        Mail::assertQueued(TemplatedMail::class);
    }

    public function test_a_channel_an_event_does_not_support_cannot_be_switched_on(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.channels', NotificationEvents::EMPLOYEE_WELCOME), [
                'channel' => 'database',
                'enabled' => '1',
            ])
            ->assertStatus(422);

        $this->assertFalse(
            NotificationTemplate::channelEnabled(NotificationEvents::EMPLOYEE_WELCOME, 'database')
        );
    }

    public function test_the_master_email_switch_still_wins(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('email_notifications_enabled', false, 'mail');

        $employee = $this->makeEmployee();

        $this->assertFalse(app(NotificationDispatcher::class)->mailEnabled(NotificationEvents::EMPLOYEE_WELCOME));
        $this->assertFalse(app(NotificationService::class)->sendWelcome($employee, 'Temp12345!'));
        Mail::assertNothingQueued();
    }

    public function test_a_switched_off_password_reset_does_not_go_out(): void
    {
        Notification::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.channels', NotificationEvents::PASSWORD_RESET_LINK), [
                'channel' => 'mail',
                'enabled' => '0',
            ]);

        $this->assertFalse(app(NotificationDispatcher::class)->mailEnabled(NotificationEvents::PASSWORD_RESET_LINK));
    }

    // ---------------------------------------------------------------- preview

    public function test_the_preview_shows_what_is_in_the_form_not_what_is_saved(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.preview', NotificationEvents::LEAVE_APPROVED), [
                'subject' => 'Draft subject for {{ employee_name }}',
                'body' => '# Heading',
            ])
            ->assertOk()
            ->assertJson(['subject' => 'Draft subject for Priya Sharma'])
            ->assertJsonPath('html', "<h1>Heading</h1>\n");

        // Nothing was saved by previewing it.
        $this->assertDatabaseCount('notification_templates', 0);
    }

    public function test_the_preview_escapes_markup_rather_than_running_it(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $response = $this->actingAs($admin->user)
            ->post(route('notification-templates.preview', NotificationEvents::LEAVE_APPROVED), [
                'body' => 'Hello <script>alert(1)</script>',
            ])
            ->assertOk();

        $this->assertStringNotContainsString('<script>', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
    }

    public function test_a_template_is_never_evaluated_as_code(): void
    {
        $renderer = app(NotificationTemplateRenderer::class);

        $rendered = $renderer->render('{{ name }}', ['name' => '{{ 7*7 }} @php echo 49; @endphp']);

        $this->assertSame('{{ 7*7 }} @php echo 49; @endphp', $rendered);
    }

    public function test_a_placeholder_with_nothing_behind_it_reads_as_a_dash(): void
    {
        $renderer = app(NotificationTemplateRenderer::class);

        $this->assertSame('Remarks: —', $renderer->render('Remarks: {{ remarks }}', []));
        $this->assertSame('Remarks: —', $renderer->render('Remarks: {{ remarks }}', ['remarks' => '']));
    }

    // -------------------------------------------------------------- test send

    public function test_an_administrator_can_send_themselves_a_test(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.test', NotificationEvents::LEAVE_APPROVED), [
                'subject' => 'Testing one two',
            ])
            ->assertRedirect();

        Mail::assertQueued(
            TemplatedMail::class,
            fn (TemplatedMail $mail) => $mail->hasTo($admin->user->email)
                && $mail->overrides['subject'] === 'Testing one two',
        );
    }

    public function test_a_test_can_be_sent_to_any_address(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('notification-templates.test', NotificationEvents::LEAVE_APPROVED), [
                'test_email' => 'colleague@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'colleague@example.com'));

        Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('colleague@example.com'));
        Mail::assertNotQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo($admin->user->email));
    }

    public function test_a_test_address_must_be_a_real_email_address(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->from(route('notification-templates.edit', NotificationEvents::LEAVE_APPROVED))
            ->post(route('notification-templates.test', NotificationEvents::LEAVE_APPROVED), [
                'test_email' => 'not-an-address',
            ])
            ->assertRedirect(route('notification-templates.edit', NotificationEvents::LEAVE_APPROVED))
            ->assertSessionHasErrors('test_email');

        Mail::assertNothingQueued();
    }

    public function test_a_test_can_still_be_sent_while_the_channel_is_switched_off(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $key = NotificationEvents::LEAVE_APPROVED;

        $this->actingAs($admin->user)->post(route('notification-templates.channels', $key), [
            'channel' => 'mail',
            'enabled' => '0',
        ]);

        // Trying the wording out is not the same as sending it to anyone.
        $this->actingAs($admin->user)
            ->post(route('notification-templates.test', $key))
            ->assertRedirect();

        Mail::assertQueued(TemplatedMail::class, fn ($mail) => $mail->hasTo($admin->user->email));
    }

    // ------------------------------------------------------------- in-app copy

    public function test_the_bell_entry_carries_the_rendered_words(): void
    {
        Mail::fake();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $admin->branch]);

        $leave = $this->applyAndApprove($employee, $admin);
        app(NotificationService::class)->notifyLeaveActioned($leave);

        $payload = json_decode(
            \DB::table('notifications')->where('notifiable_id', $employee->user_id)->value('data'),
            true,
        );

        $this->assertSame(NotificationEvents::LEAVE_APPROVED, $payload['type']);
        $this->assertSame('Leave approved', $payload['title']);
        $this->assertStringContainsString('Casual Leave', $payload['message']);
        $this->assertSame($leave->id, $payload['leave_request_id']);
        $this->assertNotEmpty($payload['url']);
    }

    /** Apply for a day of casual leave and approve it. */
    protected function applyAndApprove($employee, $approver)
    {
        $service = app(LeaveService::class);

        $leave = $service->apply($employee, [
            'leave_type_id' => LeaveType::where('code', 'CL')->value('id'),
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => 'full_day',
            'reason' => 'A day away.',
        ]);

        return $service->approve($leave, $approver->user);
    }
}
