<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Somebody punches in, closes the browser and forgets.
 *
 * The punch itself was never at risk — it is a row, not a browser session —
 * and their pay was never at risk either, because payroll counts a day present
 * on the check-in. What was lost was the hours, and the fact that anybody knew.
 */
class AbandonedPunchTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendance = app(AttendanceService::class);
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:30:00'));   // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Punching needs a location unless the organisation says otherwise. */
    protected function employee(array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2024-01-01'),
        ], $attributes));

        Setting::put('attendance_require_location', false, 'attendance');

        return $employee;
    }

    protected function enable(): void
    {
        Setting::put('attendance_auto_close_punches', true, 'attendance');
    }

    /** Punch in on Monday, never punch out, and let Tuesday arrive. */
    protected function abandonAPunch(Employee $employee, string $at = '2026-09-14 09:30:00'): Attendance
    {
        Carbon::setTestNow(Carbon::parse($at));
        $this->attendance->checkIn($employee);
        Carbon::setTestNow(Carbon::parse('2026-09-15 02:00:00'));

        return $employee->attendances()->whereDate('date', Carbon::parse($at)->toDateString())->firstOrFail();
    }

    // ------------------------------------------------- what it was, unfixed

    public function test_an_open_punch_keeps_the_pay_and_loses_the_hours(): void
    {
        $employee = $this->employee();
        $day = $this->abandonAPunch($employee);

        $this->assertNull($day->check_out, 'The day never closes on its own.');
        $this->assertSame(AttendanceStatus::Present, $day->status);
        $this->assertSame(0, $day->worked_minutes, 'The hours are the thing that is lost.');

        $totals = $this->attendance->summaryForPeriod(
            $employee, Carbon::parse('2026-09-14'), Carbon::parse('2026-09-14'),
        )['totals'];

        $this->assertSame(1.0, $totals['present_days'], 'Pay is never affected.');
        $this->assertSame(0.0, $totals['absent_days']);
    }

    public function test_a_forgotten_punch_does_not_stop_tomorrow(): void
    {
        $employee = $this->employee();
        $this->abandonAPunch($employee);

        Carbon::setTestNow(Carbon::parse('2026-09-15 09:30:00'));
        $next = $this->attendance->checkIn($employee);

        $this->assertSame('2026-09-15', $next->date->toDateString());
    }

    // ------------------------------------------------------- the switch off

    public function test_nothing_is_closed_while_the_switch_is_off(): void
    {
        $employee = $this->employee();
        $this->abandonAPunch($employee);

        $this->assertSame([], $this->attendance->closeAbandonedSessions());

        $this->artisan('hrms:close-abandoned-punches')->assertExitCode(0);

        $day = $employee->attendances()->first();
        $this->assertNull($day->check_out);
        $this->assertFalse($day->needs_correction);
    }

    // ---------------------------------------------------------- the closing

    public function test_it_closes_at_the_end_of_the_shift_not_the_hour_it_runs(): void
    {
        $employee = $this->employee();
        // The seeded shift ends at 18:00; be explicit rather than assume it.
        Shift::query()->update(['start_time' => '09:00:00', 'end_time' => '18:00:00']);

        $this->abandonAPunch($employee);
        $this->enable();

        $closed = $this->attendance->closeAbandonedSessions();

        $this->assertCount(1, $closed);
        $this->assertSame('2026-09-14 18:00:00', $closed[0]->check_out->toDateTimeString());
        $this->assertTrue($closed[0]->needs_correction);

        // 09:30 to 18:00 is 510 minutes, less the shift's nominal break.
        $this->assertGreaterThan(0, $closed[0]->worked_minutes);
        $this->assertLessThanOrEqual(510, $closed[0]->worked_minutes);
    }

    public function test_a_punch_made_after_the_shift_ended_credits_nothing(): void
    {
        $employee = $this->employee();
        Shift::query()->update(['start_time' => '09:00:00', 'end_time' => '18:00:00']);

        // Punched in at half past eight in the evening and forgot. Closing at
        // six would be before the punch; the record can vouch for no hours.
        $this->abandonAPunch($employee, '2026-09-14 20:30:00');
        $this->enable();

        $closed = $this->attendance->closeAbandonedSessions();

        $this->assertSame('2026-09-14 20:30:00', $closed[0]->check_out->toDateTimeString());
        $this->assertSame(0, $closed[0]->worked_minutes);
        $this->assertTrue($closed[0]->needs_correction);
    }

    public function test_a_night_shift_closes_the_following_morning(): void
    {
        $employee = $this->employee();
        Shift::query()->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);

        $this->abandonAPunch($employee, '2026-09-14 22:00:00');
        $this->enable();

        $closed = $this->attendance->closeAbandonedSessions();

        $this->assertSame(
            '2026-09-15 06:00:00',
            $closed[0]->check_out->toDateTimeString(),
            'An end that reads as before the start belongs to the next day.',
        );
    }

    public function test_today_is_left_alone(): void
    {
        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:30:00'));
        $this->attendance->checkIn($employee);
        $this->enable();

        // Four in the afternoon: this is somebody at work, not somebody who forgot.
        Carbon::setTestNow(Carbon::parse('2026-09-14 16:00:00'));

        $this->assertSame([], $this->attendance->closeAbandonedSessions());
        $this->assertNull($employee->attendances()->first()->check_out);
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $employee = $this->employee();
        $this->abandonAPunch($employee);
        $this->enable();

        $first = $this->attendance->closeAbandonedSessions();
        $closedAt = $first[0]->check_out->toDateTimeString();

        $this->assertSame([], $this->attendance->closeAbandonedSessions());
        $this->assertSame($closedAt, $employee->attendances()->first()->check_out->toDateTimeString());
    }

    public function test_a_break_left_running_is_closed_with_the_day(): void
    {
        $employee = $this->employee();
        Shift::query()->update(['start_time' => '09:00:00', 'end_time' => '18:00:00']);

        Carbon::setTestNow(Carbon::parse('2026-09-14 09:30:00'));
        $this->attendance->checkIn($employee);
        Carbon::setTestNow(Carbon::parse('2026-09-14 13:00:00'));
        $this->attendance->startBreak($employee, \App\Support\BreakReasons::LUNCH);

        Carbon::setTestNow(Carbon::parse('2026-09-15 02:00:00'));
        $this->enable();
        $this->attendance->closeAbandonedSessions();

        $day = $employee->attendances()->first()->load('breaks');
        $this->assertNull(
            $day->breaks->firstWhere('ended_at', null),
            'A break should not count all night either.',
        );
    }

    // ------------------------------------------------------ telling somebody

    public function test_the_employee_is_told_and_nobody_else(): void
    {
        Mail::fake();
        $employee = $this->employee();
        $this->abandonAPunch($employee);
        $this->enable();

        $this->artisan('hrms:close-abandoned-punches')->assertExitCode(0);

        Mail::assertQueued(
            \App\Mail\TemplatedMail::class,
            fn ($mail) => $mail->hasTo($employee->user->email),
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->user->id,
        ]);

        $notification = \DB::table('notifications')->where('notifiable_id', $employee->user->id)->first();
        $this->assertStringContainsString(
            NotificationEvents::PUNCH_NOT_CLOSED,
            $notification->data,
        );
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $employee = $this->employee();
        $this->abandonAPunch($employee);
        $this->enable();

        $this->artisan('hrms:close-abandoned-punches --dry-run')->assertExitCode(0);

        $this->assertNull($employee->attendances()->first()->check_out);
    }

    // -------------------------------------------------------- and the way out

    public function test_an_approved_correction_clears_the_flag(): void
    {
        $employee = $this->employee();
        Shift::query()->update(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
        $this->abandonAPunch($employee);
        $this->enable();
        $this->attendance->closeAbandonedSessions();

        $this->assertTrue($employee->attendances()->first()->needs_correction);

        // What an approved regularisation does.
        $this->attendance->record($employee, [
            'date' => '2026-09-14',
            'check_in' => '09:30',
            'check_out' => '17:15',
        ]);

        $day = $employee->attendances()->first();
        $this->assertFalse($day->needs_correction, 'Somebody has now said what the times were.');
        $this->assertSame('17:15:00', $day->check_out->toTimeString());
    }

    public function test_the_screens_say_the_punch_out_was_assumed(): void
    {
        $employee = $this->employee();
        Shift::query()->update(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
        $this->abandonAPunch($employee);
        $this->enable();
        $this->attendance->closeAbandonedSessions();

        $this->actingAs($employee->user)
            ->get(route('attendance.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertSee('assumed punch-out');

        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $this->actingAs($hr->user)
            ->get(route('attendance.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Punch-out assumed');
    }
}
