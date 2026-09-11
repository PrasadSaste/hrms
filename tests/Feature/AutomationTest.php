<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Mail\TemplatedMail;
use App\Models\AutomationRun;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Support\Automations;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * What the system does on its own: the catalogue, the record of each run, and
 * the three jobs that were written but never triggered.
 */
class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-09 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // -------------------------------------------------------- the catalogue

    public function test_every_automation_names_a_command_that_exists(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (Automations::all() as $key => $automation) {
            $this->assertContains(
                $automation['command'],
                $registered,
                $key.' names a command nothing registers.',
            );
        }
    }

    public function test_every_automation_is_actually_scheduled(): void
    {
        // The screen would otherwise promise something the scheduler never does.
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->implode(' ');

        foreach (Automations::all() as $key => $automation) {
            $this->assertStringContainsString(
                $automation['command'],
                $scheduled,
                $key.' is in the catalogue but nothing schedules it.',
            );
        }
    }

    public function test_an_automation_is_on_until_somebody_turns_it_off(): void
    {
        $this->assertTrue(Automations::enabled('people.celebrations'));

        Setting::put(Automations::settingKey('people.celebrations', 'enabled'), false, 'automation');

        $this->assertFalse(Automations::enabled('people.celebrations'));
    }

    public function test_the_hour_falls_back_when_what_is_stored_is_nonsense(): void
    {
        Setting::put(Automations::settingKey('people.celebrations', 'time'), '25:99', 'automation');

        $this->assertSame('08:00', Automations::time('people.celebrations'));
    }

    public function test_the_report_that_had_its_own_setting_still_reads_it(): void
    {
        // An installation that moved this hour before the catalogue existed
        // must not silently go back to the default.
        Setting::put('attendance_geofence_report_time', '21:00', 'attendance');

        $this->assertSame('21:00', Automations::time('attendance.location-report'));
    }

    // -------------------------------------------------------- the run record

    public function test_a_run_is_recorded_with_what_it_did(): void
    {
        Artisan::call('hrms:confirm-probation');

        $run = AutomationRun::for('people.confirm-probation')->latest('id')->firstOrFail();

        $this->assertTrue($run->succeeded());
        $this->assertNotNull($run->finished_at);
        $this->assertNotEmpty($run->summary);
    }

    public function test_a_switched_off_automation_does_nothing_but_a_person_can_still_run_it(): void
    {
        Setting::put(Automations::settingKey('people.confirm-probation', 'enabled'), false, 'automation');

        Artisan::call('hrms:confirm-probation');
        $this->assertSame(0, AutomationRun::for('people.confirm-probation')->count());

        // Pressing Run now is a decision, so it is honoured.
        Artisan::call('hrms:confirm-probation', ['--forced' => true]);
        $this->assertSame(1, AutomationRun::for('people.confirm-probation')->count());
    }

    public function test_a_run_started_by_a_person_records_who(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        Artisan::call('hrms:confirm-probation', ['--as-user' => $admin->user->id]);

        $this->assertSame(
            $admin->user->id,
            AutomationRun::for('people.confirm-probation')->latest('id')->first()->triggered_by,
        );
    }

    // --------------------------------------------------------- confirmations

    public function test_somebody_past_their_confirmation_date_is_taken_off_probation(): void
    {
        Mail::fake();
        $this->makeEmployee(Roles::HR_MANAGER);

        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'employment_status' => EmploymentStatus::Probation,
            'date_of_joining' => '2026-03-01',
            'date_of_confirmation' => '2026-09-01',
        ]);

        Artisan::call('hrms:confirm-probation');

        $this->assertSame(EmploymentStatus::Permanent, $employee->fresh()->employment_status);
        Mail::assertQueued(TemplatedMail::class);
    }

    public function test_somebody_still_within_probation_is_left_alone(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'employment_status' => EmploymentStatus::Probation,
            'date_of_confirmation' => '2026-12-01',
        ]);

        Artisan::call('hrms:confirm-probation');

        $this->assertSame(EmploymentStatus::Probation, $employee->fresh()->employment_status);
    }

    public function test_a_dry_run_confirms_nobody(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'employment_status' => EmploymentStatus::Probation,
            'date_of_confirmation' => '2026-09-01',
        ]);

        Artisan::call('hrms:confirm-probation', ['--dry-run' => true]);

        $this->assertSame(EmploymentStatus::Probation, $employee->fresh()->employment_status);
        $this->assertSame(0, AutomationRun::for('people.confirm-probation')->latest('id')->first()->affected);
    }

    // ------------------------------------------------------------ leave year

    public function test_the_leave_year_opens_without_anybody_pressing_a_button(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        Artisan::call('hrms:allocate-leave-year', ['--year' => 2027]);

        $this->assertTrue(
            LeaveAllocation::where('employee_id', $employee->id)->where('year', 2027)->exists(),
        );
    }

    public function test_opening_the_year_twice_does_not_double_anybody_up(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        Artisan::call('hrms:allocate-leave-year', ['--year' => 2027]);
        $first = LeaveAllocation::where('employee_id', $employee->id)->where('year', 2027)->count();

        Artisan::call('hrms:allocate-leave-year', ['--year' => 2027]);

        $this->assertSame(
            $first,
            LeaveAllocation::where('employee_id', $employee->id)->where('year', 2027)->count(),
        );
    }

    public function test_remaining_days_carry_into_the_new_year(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $type = LeaveType::where('carry_forward', true)->first()
            ?? LeaveType::first()->fill(['carry_forward' => true, 'max_carry_forward_days' => 10]);
        $type->forceFill(['carry_forward' => true, 'max_carry_forward_days' => 10, 'accrual' => 'yearly'])->save();

        LeaveAllocation::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026],
            ['allocated_days' => 12, 'used_days' => 4, 'carried_forward_days' => 0],
        );

        Artisan::call('hrms:allocate-leave-year', ['--year' => 2027]);

        $this->assertSame(
            8.0,
            (float) LeaveAllocation::where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('year', 2027)
                ->value('carried_forward_days'),
        );
    }

    // --------------------------------------------------------- celebrations

    public function test_a_birthday_today_is_told_to_somebody(): void
    {
        Mail::fake();
        $this->makeEmployee(Roles::HR_MANAGER);
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_birth' => '1992-09-09']);

        Artisan::call('hrms:celebrations');

        Mail::assertQueued(TemplatedMail::class);
        $this->assertGreaterThan(0, AutomationRun::for('people.celebrations')->latest('id')->first()->affected);
    }

    public function test_a_first_day_is_not_an_anniversary(): void
    {
        Mail::fake();
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2026-09-09', 'date_of_birth' => '1992-01-01']);

        Artisan::call('hrms:celebrations');

        $this->assertSame(0, AutomationRun::for('people.celebrations')->latest('id')->first()->affected);
    }

    public function test_a_leap_day_birthday_is_not_skipped_three_years_in_four(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2027-02-28 09:00:00'));
        $this->makeEmployee(Roles::HR_MANAGER);
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_birth' => '1996-02-29']);

        Artisan::call('hrms:celebrations');

        $this->assertGreaterThan(0, AutomationRun::for('people.celebrations')->latest('id')->first()->affected);
    }

    public function test_nothing_is_sent_when_nobody_is_celebrating(): void
    {
        Mail::fake();
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_birth' => '1992-01-01', 'date_of_joining' => '2020-05-05']);

        Artisan::call('hrms:celebrations');

        Mail::assertNothingQueued();
    }

    // ------------------------------------------------------------ the screen

    public function test_the_console_shows_what_ran_and_what_it_did(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Artisan::call('hrms:confirm-probation');

        $this->actingAs($admin->user)->get(route('automations.index'))
            ->assertOk()
            ->assertSee('Confirm people whose probation has ended')
            ->assertSee('Nobody reached the end of probation.')
            ->assertSee('hrms:accrue-leave');
    }

    public function test_the_hour_can_be_moved_from_the_screen(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('automations.update', 'people.celebrations'), ['time' => '07:15', 'enabled' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('07:15', Automations::time('people.celebrations'));
    }

    public function test_moving_the_report_hour_writes_the_setting_it_always_used(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('automations.update', 'attendance.location-report'), ['time' => '20:45', 'enabled' => '1'])
            ->assertRedirect();

        $this->assertSame('20:45', Setting::get('attendance_geofence_report_time'));
    }

    public function test_an_impossible_hour_is_refused(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('automations.update', 'people.celebrations'), ['time' => '99:99'])
            ->assertSessionHasErrors('time');
    }

    public function test_an_automation_that_does_not_exist_is_not_found(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('automations.run', 'nothing.here'))
            ->assertNotFound();
    }

    public function test_an_ordinary_employee_cannot_see_or_run_anything(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get(route('automations.index'))->assertForbidden();
        $this->actingAs($employee->user)
            ->post(route('automations.run', 'people.celebrations'))
            ->assertForbidden();
    }

    public function test_running_one_from_the_screen_records_it(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('automations.run', 'people.confirm-probation'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = AutomationRun::for('people.confirm-probation')->latest('id')->firstOrFail();
        $this->assertSame($admin->user->id, $run->triggered_by);
    }

    public function test_every_automation_has_a_notification_or_a_visible_outcome(): void
    {
        // A job whose work nobody can see is a job nobody will trust.
        foreach (Automations::all() as $key => $automation) {
            $this->assertNotEmpty($automation['summary'], $key.' does not say what it does.');
            $this->assertNotEmpty($automation['detail'], $key.' does not say why it exists.');
        }

        $this->assertTrue(NotificationEvents::exists(NotificationEvents::PROBATION_CONFIRMED));
        $this->assertTrue(NotificationEvents::exists(NotificationEvents::CELEBRATIONS_TODAY));
    }
}
