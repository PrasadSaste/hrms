<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\BreakReasons;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A working day is made of several punches and the breaks between them.
 */
class AttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        // A Wednesday, well inside working hours.
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:30:00'));
        $this->attendance = app(AttendanceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------ several sessions

    public function test_someone_can_punch_in_and_out_more_than_once_a_day(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->punch('out', $employee, '12:30');
        $this->punch('in', $employee, '14:00');
        $attendance = $this->punch('out', $employee, '18:00');

        $this->assertCount(2, $attendance->sessions);
        $this->assertSame('09:30', $attendance->check_in->format('H:i'), 'The day starts at the first punch in.');
        $this->assertSame('18:00', $attendance->check_out->format('H:i'), 'The day ends at the last punch out.');
        $this->assertSame(7 * 60, $attendance->sessions->sum('duration_minutes'), 'Three hours plus four.');
        // No break was recorded, so the shift's own unpaid hour still comes off.
        $this->assertSame(7 * 60 - 60, $attendance->worked_minutes);
    }

    public function test_a_second_punch_in_is_refused_while_one_is_open(): void
    {
        $employee = $this->makeEmployee();
        $this->punch('in', $employee, '09:30');

        $this->expectException(ValidationException::class);
        $this->punch('in', $employee, '10:00');
    }

    public function test_punching_out_without_being_punched_in_is_refused(): void
    {
        $employee = $this->makeEmployee();
        $this->punch('in', $employee, '09:30');
        $this->punch('out', $employee, '12:30');

        $this->expectException(ValidationException::class);
        $this->punch('out', $employee, '13:00');
    }

    public function test_the_day_is_only_closed_once_nobody_is_punched_in(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->punch('out', $employee, '12:30');
        $attendance = $this->punch('in', $employee, '14:00');

        $this->assertNull($attendance->check_out, 'The day closed while a session was still running.');
        $this->assertTrue($attendance->isPunchedIn());
    }

    // ---------------------------------------------------------------- breaks

    public function test_a_break_is_recorded_with_its_reason_and_taken_off_the_working_time(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->break('start', $employee, '13:00', BreakReasons::LUNCH, 'Canteen');
        $this->break('end', $employee, '13:45');
        $attendance = $this->punch('out', $employee, '18:30');

        $break = $attendance->breaks->first();

        $this->assertSame(BreakReasons::LUNCH, $break->reason);
        $this->assertSame('Canteen', $break->comment);
        $this->assertSame(45, $break->duration_minutes);

        // Nine hours at work, forty-five minutes of it on a break.
        $this->assertSame(9 * 60, $attendance->sessions->sum('duration_minutes'));
        $this->assertSame(45, $attendance->break_minutes);
        $this->assertSame(9 * 60 - 45, $attendance->worked_minutes);
    }

    public function test_several_breaks_add_up(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:00');
        $this->break('start', $employee, '11:00', BreakReasons::CLIENT_CALL);
        $this->break('end', $employee, '11:20');
        $this->break('start', $employee, '13:00', BreakReasons::LUNCH);
        $this->break('end', $employee, '13:40');
        $attendance = $this->punch('out', $employee, '18:00');

        $this->assertCount(2, $attendance->breaks);
        $this->assertSame(60, $attendance->break_minutes);
        $this->assertSame(9 * 60 - 60, $attendance->worked_minutes);
    }

    public function test_a_break_needs_somebody_to_be_punched_in(): void
    {
        $employee = $this->makeEmployee();

        $this->expectException(ValidationException::class);
        $this->break('start', $employee, '10:00', BreakReasons::LUNCH);
    }

    public function test_a_second_break_is_refused_while_one_is_running(): void
    {
        $employee = $this->makeEmployee();
        $this->punch('in', $employee, '09:30');
        $this->break('start', $employee, '13:00', BreakReasons::LUNCH);

        $this->expectException(ValidationException::class);
        $this->break('start', $employee, '13:10', BreakReasons::PERSONAL_CALL);
    }

    public function test_an_unknown_reason_is_refused(): void
    {
        $employee = $this->makeEmployee();
        $this->punch('in', $employee, '09:30');

        $this->expectException(ValidationException::class);
        $this->break('start', $employee, '13:00', 'nipping-out');
    }

    public function test_punching_out_ends_a_break_somebody_forgot(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->break('start', $employee, '17:00', BreakReasons::CLIENT_VISIT);
        $attendance = $this->punch('out', $employee, '18:00');

        $break = $attendance->breaks->first();

        $this->assertNotNull($break->ended_at, 'A break was left running after the day ended.');
        $this->assertSame(60, $break->duration_minutes);
        $this->assertFalse($attendance->isOnBreak());
    }

    public function test_punching_in_is_refused_while_a_break_is_running(): void
    {
        $employee = $this->makeEmployee();
        $this->punch('in', $employee, '09:30');
        $this->break('start', $employee, '13:00', BreakReasons::LUNCH);

        // Ending the session is what a break needs, not a second punch in.
        $this->expectException(ValidationException::class);
        $this->punch('in', $employee, '13:30');
    }

    // -------------------------------------------------------- what it adds up to

    public function test_a_day_of_short_sessions_is_a_half_day(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->punch('out', $employee, '11:00');
        $attendance = $this->punch('in', $employee, '12:00');
        $attendance = $this->punch('out', $employee, '13:30');

        // Three hours at work, less the shift's unpaid hour.
        $this->assertSame(180, $attendance->sessions->sum('duration_minutes'));
        $this->assertSame(120, $attendance->worked_minutes);
        $this->assertSame(AttendanceStatus::HalfDay, $attendance->status);
    }

    public function test_the_shift_break_still_applies_when_nobody_uses_the_button(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $attendance = $this->punch('out', $employee, '18:30');

        // The shift carries a 60 minute unpaid break, and no break was recorded.
        $this->assertSame(60, $attendance->break_minutes);
        $this->assertSame(9 * 60 - 60, $attendance->worked_minutes);
    }

    public function test_recorded_breaks_replace_the_shift_break_rather_than_stacking(): void
    {
        $employee = $this->makeEmployee();

        $this->punch('in', $employee, '09:30');
        $this->break('start', $employee, '13:00', BreakReasons::LUNCH);
        $this->break('end', $employee, '13:20');
        $attendance = $this->punch('out', $employee, '18:30');

        $this->assertSame(20, $attendance->break_minutes, 'The shift break was deducted on top of a real one.');
    }

    // --------------------------------------------------------------- screens

    public function test_the_screens_drive_the_whole_day(): void
    {
        $employee = $this->makeEmployee();

        // Time has to move between the requests, or the punch out lands on the
        // same second as the punch in and is refused.
        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'), $this->position())
            ->assertSessionHasNoErrors();

        Carbon::setTestNow(Carbon::parse('2026-06-10 11:00:00'));
        $this->actingAs($employee->user)->post(route('attendance.break.start'), [
            'reason' => BreakReasons::TEAM_MEETING,
            'comment' => 'Weekly stand-up',
        ])->assertSessionHasNoErrors();

        Carbon::setTestNow(Carbon::parse('2026-06-10 11:30:00'));
        $this->actingAs($employee->user)->post(route('attendance.break.end'))->assertSessionHasNoErrors();

        Carbon::setTestNow(Carbon::parse('2026-06-10 12:30:00'));
        $this->actingAs($employee->user)
            ->post(route('attendance.check-out'), $this->position())
            ->assertSessionHasNoErrors();

        // And again, later the same day.
        Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));
        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'), $this->position())
            ->assertSessionHasNoErrors();

        $attendance = Attendance::with(['sessions', 'breaks'])
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertCount(2, $attendance->sessions);
        $this->assertCount(1, $attendance->breaks);
        $this->assertSame('Weekly stand-up', $attendance->breaks->first()->comment);
    }

    public function test_the_api_can_take_a_break_too(): void
    {
        $employee = $this->makeEmployee();
        $token = $employee->user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $this->postJson(route('api.attendance.check-in'), $this->position(), $headers)->assertCreated();

        $this->getJson(route('api.attendance.break-reasons'), $headers)
            ->assertOk()
            ->assertJsonFragment(['key' => BreakReasons::LUNCH]);

        Carbon::setTestNow(Carbon::parse('2026-06-10 13:00:00'));
        $this->postJson(route('api.attendance.break.start'), ['reason' => BreakReasons::LUNCH], $headers)
            ->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-06-10 13:40:00'));
        $this->postJson(route('api.attendance.break.end'), [], $headers)->assertOk();

        $this->assertDatabaseCount('attendance_breaks', 1);
    }

    public function test_a_manual_entry_by_hr_replaces_the_sessions(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->punch('in', $employee, '09:30');
        $this->punch('out', $employee, '11:00');

        $this->attendance->record($employee, [
            'date' => '2026-06-10',
            'check_in' => '09:00',
            'check_out' => '18:00',
        ], $hr->user->id);

        $attendance = Attendance::with('sessions')->where('employee_id', $employee->id)->firstOrFail();

        $this->assertCount(1, $attendance->sessions, 'HR\'s correction sat beside the old sessions.');
        $this->assertSame('09:00', $attendance->check_in->format('H:i'));
        $this->assertSame('18:00', $attendance->check_out->format('H:i'));
    }

    /** A punch at a given time on the test day. */
    protected function punch(string $direction, $employee, string $time): Attendance
    {
        $at = Carbon::parse('2026-06-10 '.$time);

        return $direction === 'in'
            ? $this->attendance->checkIn($employee, $at, 'web', '127.0.0.1', $this->location())
            : $this->attendance->checkOut($employee, $at, 'web', '127.0.0.1', $this->location());
    }

    protected function break(string $action, $employee, string $time, ?string $reason = null, ?string $comment = null): Attendance
    {
        $at = Carbon::parse('2026-06-10 '.$time);

        return $action === 'start'
            ? $this->attendance->startBreak($employee, $reason, $comment, $at)
            : $this->attendance->endBreak($employee, $at);
    }

    protected function location(): PunchLocation
    {
        return new PunchLocation(18.5204, 73.8567, 12);
    }

    protected function position(): array
    {
        return ['latitude' => 18.5204, 'longitude' => 73.8567, 'accuracy' => 12];
    }
}
