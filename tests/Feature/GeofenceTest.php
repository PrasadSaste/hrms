<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Branch;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Support\Geo;
use App\Support\NotificationEvents;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Punches measured against the branch they belong to.
 */
class GeofenceTest extends TestCase
{
    use RefreshDatabase;

    /** The office. */
    private const OFFICE_LAT = 17.4401000;

    private const OFFICE_LNG = 78.3489000;

    protected AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:30:00'));
        $this->attendance = app(AttendanceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function officeBranch(array $attributes = []): Branch
    {
        return $this->makeBranch(array_merge([
            'latitude' => self::OFFICE_LAT,
            'longitude' => self::OFFICE_LNG,
        ], $attributes));
    }

    /** A point a given distance due north of the office. */
    protected function metresNorth(float $metres): PunchLocation
    {
        return new PunchLocation(
            latitude: self::OFFICE_LAT + ($metres / 111_320),
            longitude: self::OFFICE_LNG,
            accuracy: 12,
        );
    }

    // ------------------------------------------------------------ the distance

    public function test_the_distance_between_two_points_is_measured_in_metres(): void
    {
        // One degree of latitude is about 111.32 km anywhere on the globe.
        $metres = Geo::distanceInMetres(0.0, 0.0, 1.0, 0.0);

        $this->assertEqualsWithDelta(111_195, $metres, 100);
    }

    public function test_the_same_point_is_no_distance_at_all(): void
    {
        $this->assertSame(
            0.0,
            Geo::distanceInMetres(self::OFFICE_LAT, self::OFFICE_LNG, self::OFFICE_LAT, self::OFFICE_LNG),
        );
    }

    public function test_a_distance_is_described_the_way_a_person_would_say_it(): void
    {
        $this->assertSame('180 m', Geo::describeDistance(180));
        $this->assertSame('1.4 km', Geo::describeDistance(1420));
        $this->assertSame('—', Geo::describeDistance(null));
    }

    // -------------------------------------------------------------- the punch

    public function test_a_punch_at_the_office_is_recorded_as_inside_the_radius(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(40));
        $session = $attendance->sessions->first();

        $this->assertEqualsWithDelta(40, $session->check_in_distance_metres, 2);
        $this->assertFalse($session->check_in_outside_geofence);
    }

    public function test_a_punch_beyond_the_radius_is_flagged(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(450));
        $session = $attendance->sessions->first();

        $this->assertEqualsWithDelta(450, $session->check_in_distance_metres, 5);
        $this->assertTrue($session->check_in_outside_geofence);
    }

    public function test_the_punch_is_still_recorded_when_it_is_flagged(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(5_000));

        // A client visit is not a reason to lose somebody's attendance.
        $this->assertNotNull($attendance->check_in);
        $this->assertTrue($attendance->isPunchedIn());
    }

    public function test_each_punch_is_measured_on_its_own(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(30));

        Carbon::setTestNow(Carbon::parse('2026-06-10 18:30:00'));
        $attendance = $this->attendance->checkOut($employee, null, 'web', '127.0.0.1', $this->metresNorth(900));

        $session = $attendance->sessions->first();

        $this->assertFalse($session->check_in_outside_geofence, 'They arrived at the office.');
        $this->assertTrue($session->check_out_outside_geofence, 'They left from somewhere else.');
    }

    public function test_a_branch_with_its_own_radius_uses_that_instead(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'branch' => $this->officeBranch(['geofence_radius_metres' => 1000]),
        ]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(450));

        $this->assertFalse(
            $attendance->sessions->first()->check_in_outside_geofence,
            'A campus given a kilometre should not flag a punch 450 m out.',
        );
    }

    public function test_a_branch_without_coordinates_is_never_flagged(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->makeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(9_000));
        $session = $attendance->sessions->first();

        $this->assertNull($session->check_in_distance_metres);
        $this->assertFalse($session->check_in_outside_geofence);
    }

    public function test_nothing_is_measured_when_the_check_is_switched_off(): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_geofence_enabled', false, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(9_000));

        $this->assertNull($attendance->sessions->first()->check_in_distance_metres);
    }

    public function test_a_punch_without_a_position_is_not_measured(): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_require_location', false, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee);

        $this->assertNull($attendance->sessions->first()->check_in_distance_metres);
        $this->assertFalse($attendance->sessions->first()->check_in_outside_geofence);
    }

    // ------------------------------------------------------------- the alerts

    public function test_the_nominated_people_are_emailed_about_a_flagged_punch(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com, ops@example.com', 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(600));

        Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('hr@example.com')
            && $mail->eventKey === NotificationEvents::GEOFENCE_BREACH);
        Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('ops@example.com'));
    }

    public function test_nobody_is_told_about_a_punch_inside_the_radius(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(50));

        Mail::assertNothingQueued();
    }

    public function test_the_employee_is_not_told_on_themselves(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(600));

        Mail::assertNotQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo($employee->email));
    }

    public function test_with_nobody_nominated_it_falls_back_to_hr_and_administrators(): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', '', 'attendance');

        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $recipients = app(GeofenceService::class)->recipients($employee)->pluck('email');

        $this->assertTrue($recipients->contains($hr->user->email), 'An alert must never be sent nowhere.');
    }

    public function test_an_address_that_belongs_to_a_login_also_gets_it_in_the_bell_menu(): void
    {
        $this->seedReferenceData();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        Setting::put('attendance_geofence_alert_recipients', $hr->user->email, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(600));

        $this->assertSame(1, $hr->user->fresh()->notifications()->count());
    }

    public function test_the_managers_are_included_only_when_that_is_asked_for(): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');

        $branch = $this->officeBranch();
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER, ['branch' => $branch]);
        $branch->update(['manager_id' => $manager->id]);

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branch->fresh()]);
        $geofence = app(GeofenceService::class);

        $this->assertFalse($geofence->recipients($employee)->pluck('email')->contains($manager->user->email));

        Setting::put('attendance_geofence_alert_managers', true, 'attendance');

        $this->assertTrue($geofence->recipients($employee)->pluck('email')->contains($manager->user->email));
    }

    public function test_an_address_that_is_not_an_address_is_dropped_rather_than_saved(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->put(route('settings.update'), [
            'company_name' => 'Beyond Sure',
            'brand_color' => '#2563eb',
            'brand_secondary_color' => '#0d9488',
            'brand_tertiary_color' => '#f59e0b',
            'theme_surface' => 'light',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd M Y',
            'employee_code_prefix' => 'EMP',
            'employee_code_padding' => 4,
            'financial_year_start_month' => 4,
            'attendance_geofence_alert_recipients' => "hr@example.com\nnot-an-address\nops@example.com,",
        ])->assertRedirect();

        $this->assertSame(
            'hr@example.com, ops@example.com',
            Setting::get('attendance_geofence_alert_recipients'),
        );
    }

    // ------------------------------------------------------------- the report

    public function test_the_days_flagged_punches_are_listed_for_the_report(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(700));
        Carbon::setTestNow(Carbon::parse('2026-06-10 18:30:00'));
        $this->attendance->checkOut($employee, null, 'web', '127.0.0.1', $this->metresNorth(20));

        $rows = app(GeofenceService::class)->flaggedPunches(Carbon::parse('2026-06-10'));

        $this->assertCount(1, $rows, 'Only the punch that was out of range belongs in the report.');
        $this->assertSame('check_in', $rows->first()['punch']);
    }

    public function test_the_report_is_emailed_to_the_nominated_people(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(700));

        $this->artisan('hrms:location-report', ['--date' => '2026-06-10'])->assertSuccessful();

        Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) use ($employee) {
            return $mail->eventKey === NotificationEvents::GEOFENCE_REPORT
                && $mail->hasTo('hr@example.com')
                && str_contains($mail->data['report_table'], $employee->full_name);
        });
    }

    public function test_the_report_goes_out_on_a_quiet_day_too(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');

        $this->artisan('hrms:location-report', ['--date' => '2026-06-10'])->assertSuccessful();

        // Silence from a report and silence from a stopped scheduler look the
        // same, so the quiet day is worth saying out loud.
        Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::GEOFENCE_REPORT
            && str_contains($mail->data['summary'], 'No punches'));
    }

    public function test_the_report_can_be_switched_off(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        Setting::put('attendance_geofence_alert_recipients', 'hr@example.com', 'attendance');
        Setting::put('attendance_geofence_daily_report', false, 'attendance');

        $this->artisan('hrms:location-report')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    // ------------------------------------------------------------- the screen

    public function test_hr_sees_the_days_flagged_punches(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $attendance = $this->attendance->checkIn($employee, null, 'web', '127.0.0.1', $this->metresNorth(640));
        $distance = Geo::describeDistance($attendance->sessions->first()->check_in_distance_metres);

        $this->actingAs($hr->user)
            ->get(route('attendance.location-alerts', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee($employee->full_name)
            ->assertSee($distance);
    }

    public function test_a_branch_manager_does_not_see_another_branch(): void
    {
        $theirBranch = $this->officeBranch();
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER, ['branch' => $theirBranch]);

        $elsewhere = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);
        $this->attendance->checkIn($elsewhere, null, 'web', '127.0.0.1', $this->metresNorth(640));

        $this->actingAs($manager->user)
            ->get(route('attendance.location-alerts', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertDontSee($elsewhere->full_name);
    }

    public function test_an_employee_may_not_open_the_screen_at_all(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $this->officeBranch()]);

        $this->actingAs($employee->user)
            ->get(route('attendance.location-alerts'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- the branch

    public function test_a_branch_is_saved_with_its_coordinates(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('branches.store'), [
            'name' => 'Hyderabad', 'code' => 'HYD',
            'timezone' => 'Asia/Kolkata',
            'work_start_time' => '09:30', 'work_end_time' => '18:30',
            'working_days' => [1, 2, 3, 4, 5],
            'status' => 'active',
            'latitude' => self::OFFICE_LAT, 'longitude' => self::OFFICE_LNG,
            'geofence_radius_metres' => 350,
        ])->assertRedirect();

        $branch = Branch::where('code', 'HYD')->firstOrFail();

        $this->assertEqualsWithDelta(self::OFFICE_LAT, $branch->latitude, 0.0000001);
        $this->assertSame(350, $branch->geofence_radius_metres);
        $this->assertSame(350, $branch->geofenceRadius());
    }

    public function test_half_a_coordinate_is_refused(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('branches.store'), [
            'name' => 'Hyderabad', 'code' => 'HYD2',
            'timezone' => 'Asia/Kolkata',
            'work_start_time' => '09:30', 'work_end_time' => '18:30',
            'working_days' => [1, 2, 3, 4, 5],
            'status' => 'active',
            'latitude' => self::OFFICE_LAT,
        ])->assertSessionHasErrors('longitude');
    }

    public function test_a_branch_left_empty_falls_back_to_the_organisation_radius(): void
    {
        $this->seedReferenceData();
        Setting::put('attendance_geofence_radius', 500, 'attendance');

        $this->assertSame(500, $this->officeBranch()->geofenceRadius());
    }
}
