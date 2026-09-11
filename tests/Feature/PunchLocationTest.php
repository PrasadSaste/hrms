<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PunchLocationTest extends TestCase
{
    use RefreshDatabase;

    /** Bengaluru, roughly. */
    private const LAT = 12.9352;

    private const LNG = 77.6245;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:25:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function requireLocation(bool $required = true): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_require_location', $required, 'attendance');
    }

    // ---------------------------------------------------------------- the web

    public function test_a_punch_without_a_location_is_refused(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'))
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertSame(0, Attendance::count(), 'Nothing may be recorded without a position.');
    }

    public function test_the_refusal_explains_what_to_do(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $response = $this->actingAs($employee->user)->post(route('attendance.check-in'));

        $response->assertSessionHasErrors('latitude');

        $this->assertStringContainsString(
            'Allow location access',
            $response->getSession()->get('errors')->first('latitude'),
        );
    }

    public function test_a_punch_with_a_location_is_recorded_with_its_coordinates(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), [
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'accuracy' => 18,
        ])->assertRedirect();

        $attendance = Attendance::firstOrFail();

        $this->assertEqualsWithDelta(self::LAT, $attendance->check_in_latitude, 0.0000001);
        $this->assertEqualsWithDelta(self::LNG, $attendance->check_in_longitude, 0.0000001);
        $this->assertSame(18, $attendance->check_in_accuracy);
        $this->assertNotNull($attendance->check_in);
    }

    public function test_checking_out_records_its_own_position(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), [
            'latitude' => self::LAT, 'longitude' => self::LNG, 'accuracy' => 18,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-10 18:30:00'));

        // Checked out from a different place, as happens on a client visit.
        $this->actingAs($employee->user)->post(route('attendance.check-out'), [
            'latitude' => 12.9716, 'longitude' => 77.5946, 'accuracy' => 42,
        ])->assertRedirect();

        $attendance = Attendance::firstOrFail();

        $this->assertEqualsWithDelta(12.9716, $attendance->check_out_latitude, 0.0000001);
        $this->assertEqualsWithDelta(77.5946, $attendance->check_out_longitude, 0.0000001);
        $this->assertSame(42, $attendance->check_out_accuracy);
        $this->assertNotSame($attendance->check_in_latitude, $attendance->check_out_latitude);
    }

    public function test_checking_out_without_a_location_is_refused(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), [
            'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);

        $this->actingAs($employee->user)
            ->post(route('attendance.check-out'))
            ->assertSessionHasErrors(['latitude']);

        $this->assertNull(Attendance::firstOrFail()->check_out);
    }

    public function test_nonsense_coordinates_are_rejected(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), [
            'latitude' => 999, 'longitude' => 'north',
        ])->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertSame(0, Attendance::count());
    }

    public function test_the_requirement_can_be_switched_off(): void
    {
        $this->requireLocation(false);
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $attendance = Attendance::firstOrFail();

        $this->assertNotNull($attendance->check_in);
        $this->assertNull($attendance->check_in_latitude);
    }

    // ---------------------------------------------------------------- the API

    public function test_the_api_refuses_a_punch_without_a_location(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->postJson('/api/v1/attendance/check-in')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertSame(0, Attendance::count());
    }

    public function test_the_api_records_coordinates_and_returns_them(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::LAT, 'longitude' => self::LNG, 'accuracy' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('attendance.check_in_location.latitude', self::LAT)
            ->assertJsonPath('attendance.check_in_location.accuracy_metres', 12);
    }

    public function test_the_api_tells_a_client_that_a_location_is_needed(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('location_required', true);

        Setting::put('attendance_require_location', false, 'attendance');

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('location_required', false);
    }

    // ------------------------------------------------------------ the service

    public function test_the_service_refuses_a_punch_with_no_position(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->expectException(ValidationException::class);

        app(AttendanceService::class)->checkIn($employee, null, 'api', '127.0.0.1', PunchLocation::none());
    }

    public function test_a_manual_entry_by_hr_needs_no_location(): void
    {
        $this->requireLocation();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        // HR records attendance on someone's behalf; they are not at that place.
        $this->actingAs($hr->user)->post(route('attendance.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-06-09',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'status' => 'present',
            'remarks' => 'Biometric device was offline.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Attendance::count());
    }

    // ----------------------------------------------------------- the location

    public function test_a_position_without_a_name_describes_itself(): void
    {
        $location = new PunchLocation(self::LAT, self::LNG, 18);

        $this->assertTrue($location->hasCoordinates());
        $this->assertSame('12.93520, 77.62450', $location->describe());
    }

    public function test_a_supplied_place_name_is_kept(): void
    {
        $location = new PunchLocation(self::LAT, self::LNG, 18, 'Bengaluru Head Office');

        $this->assertSame('Bengaluru Head Office', $location->describe());
    }

    public function test_an_empty_position_describes_nothing(): void
    {
        $location = PunchLocation::none();

        $this->assertFalse($location->hasCoordinates());
        $this->assertNull($location->describe());
    }

    public function test_a_recorded_punch_offers_a_map_link(): void
    {
        $this->requireLocation();
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), [
            'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);

        $attendance = Attendance::firstOrFail();

        $this->assertStringContainsString('openstreetmap.org', $attendance->mapUrl('check_in'));
        $this->assertNull($attendance->mapUrl('check_out'), 'No fix, no link.');
    }
}
