<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\DashboardService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What the dashboard says about the request queue.
 *
 * The drawing is a browser's business; the arithmetic behind it is not. These
 * are the numbers the charts are only a picture of.
 */
class DashboardChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    protected function request(array $attributes = []): LeaveRequest
    {
        static $n = 0;
        $n++;

        // makeEmployee seeds the reference data, so the leave types exist by
        // the time one is asked for.
        $employee = $this->makeEmployee();

        return LeaveRequest::create(array_merge([
            'reference' => 'LR-TEST-'.$n,
            'employee_id' => $employee->id,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-21',
            'day_type' => 'full_day',
            'total_days' => 2,
            'status' => 'pending',
            'applied_on' => '2026-09-01',
            'reason' => 'A test request.',
            'leave_type_id' => LeaveType::firstOrFail()->id,
        ], $attributes));
    }

    public function test_the_trend_counts_raised_against_decided_by_week(): void
    {
        $this->request(['applied_on' => '2026-09-01']);
        $this->request(['applied_on' => '2026-09-02', 'status' => 'approved', 'actioned_at' => '2026-09-03 09:00:00']);

        $trend = collect($this->service()->requestTrend(null, Carbon::today()));

        $this->assertCount(8, $trend, 'Eight weeks, whether or not anything happened in them.');
        $this->assertSame(2, $trend->sum('raised'));
        $this->assertSame(1, $trend->sum('decided'));

        // Both fall in the week beginning Monday 31 August.
        $week = $trend->firstWhere('label', '31 Aug');
        $this->assertSame(2, $week['raised']);
        $this->assertSame(1, $week['decided']);
    }

    public function test_the_mix_counts_each_state_and_names_it(): void
    {
        $this->request(['status' => 'pending']);
        $this->request(['status' => 'approved', 'actioned_at' => now()]);
        $this->request(['status' => 'approved', 'actioned_at' => now()]);
        $this->request(['status' => 'rejected', 'actioned_at' => now()]);

        $mix = collect($this->service()->requestMix(null, Carbon::today()))->keyBy('status');

        $this->assertSame(1, $mix['warning']['value']);
        $this->assertSame(2, $mix['good']['value']);
        $this->assertSame(1, $mix['critical']['value']);

        // Approved-green against rejected-red is 4.1 apart under deuteranopia.
        // Every state therefore carries an icon and a written label, so the
        // colour is the last channel rather than the only one.
        foreach ($mix as $segment) {
            $this->assertNotEmpty($segment['icon']);
            $this->assertNotEmpty($segment['label']);
        }
    }

    public function test_how_long_a_decision_takes(): void
    {
        $this->request(['applied_on' => '2026-09-01', 'status' => 'approved', 'actioned_at' => '2026-09-05 12:00:00']);
        $this->request(['applied_on' => '2026-09-01', 'status' => 'approved', 'actioned_at' => '2026-09-03 12:00:00']);

        $speed = $this->service()->decisionSpeed(null, Carbon::today());

        $this->assertSame(2, $speed['decided']);

        // Four and a half days and two and a half, not four and two: applied_on
        // is a date at midnight and the decisions came at noon. Half a day is
        // real and worth keeping — a queue that turns round in half a day and
        // one that takes a day and a half are not the same queue.
        $this->assertSame(3.5, $speed['days']);
    }

    /**
     * A request applied for a date still to come has waited no days.
     *
     * The difference between two dates is signed, and without a floor the
     * dashboard cheerfully reported "the longest wait so far is -6 days".
     */
    public function test_a_wait_is_never_negative(): void
    {
        $this->request(['applied_on' => '2026-09-30', 'status' => 'pending']);
        $this->request(['applied_on' => '2026-09-20', 'status' => 'approved', 'actioned_at' => '2026-09-01 09:00:00']);

        $speed = $this->service()->decisionSpeed(null, Carbon::today());

        $this->assertSame(0, $speed['oldest_waiting']);
        $this->assertGreaterThanOrEqual(0, $speed['days']);
    }

    public function test_leave_by_type_counts_only_days_actually_approved(): void
    {
        $first = $this->request(['status' => 'approved', 'total_days' => 3]);

        $this->request([
            'status' => 'pending',
            'total_days' => 9,
            'leave_type_id' => $first->leave_type_id,
        ]);

        $byType = $this->service()->leaveByType(null, Carbon::today());

        $this->assertCount(1, $byType);
        $this->assertSame(3.0, $byType->first()['value'], 'A request nobody has approved is not leave taken.');
    }

    /*
    |--------------------------------------------------------------------------
    | Three dashboards, not one
    |--------------------------------------------------------------------------
    */

    public function test_somebody_answerable_for_others_sees_the_organisation(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $data = $this->service()->forUser($hr->user);

        $this->assertArrayHasKey('org', $data);
        $this->assertArrayHasKey('requests', $data);
        $this->assertArrayNotHasKey(
            'mine',
            $data,
            'Their own fortnight is on My Attendance; here it only makes the page longer.',
        );
    }

    public function test_an_employee_sees_their_own_shape_and_nobody_elses(): void
    {
        $employee = $this->makeEmployee();

        $data = $this->service()->forUser($employee->user);

        $this->assertArrayHasKey('mine', $data);
        $this->assertArrayNotHasKey('org', $data);
        $this->assertArrayNotHasKey('requests', $data, 'The whole queue is not an employee\'s business.');
        $this->assertCount(14, $data['mine']['attendance']);
    }

    public function test_the_dashboard_renders_its_charts_with_a_table_behind_each(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $this->request(['status' => 'approved', 'actioned_at' => now()]);

        $this->actingAs($hr->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('chart__legend', escape: false)
            // Nothing is gated behind hovering: every chart carries its
            // figures, which is also the relief the contrast rule asks for.
            ->assertSee('See the figures');
    }
}
