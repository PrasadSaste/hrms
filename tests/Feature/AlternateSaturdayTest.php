<?php

namespace Tests\Feature;

use App\Enums\DayType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\DataImportService;
use App\Services\LeaveService;
use App\Services\WorkCalendar;
use App\Support\ImportTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A six-day week with some of its Saturdays off.
 *
 * September 2026 is a convenient month to reason about: its Saturdays fall on
 * the 5th, 12th, 19th and 26th, so the first is the 5th and the third is the
 * 19th.
 */
class AlternateSaturdayTest extends TestCase
{
    use RefreshDatabase;

    protected WorkCalendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00'));
        $this->calendar = app(WorkCalendar::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Somebody at a six-day branch with the 1st and 3rd Saturday off. */
    protected function sixDayEmployee(array $saturdayOffs = [1, 3]): Employee
    {
        $branch = $this->makeBranch([
            'working_days' => [1, 2, 3, 4, 5, 6],
            'saturday_offs' => $saturdayOffs,
        ]);

        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'branch' => $branch,
            'date_of_joining' => '2020-01-01',
        ]);

        // The shift governs the week where it names its own days, so it has to
        // agree that Saturday is worked before the branch rule can apply.
        $employee->shift?->update(['working_days' => [1, 2, 3, 4, 5, 6]]);

        return $employee->fresh(['branch', 'shift']);
    }

    // ------------------------------------------------------ which Saturday

    public function test_a_date_knows_which_of_its_weekday_it_is(): void
    {
        $this->assertSame(1, $this->calendar->weekOfMonth(Carbon::parse('2026-09-05')));
        $this->assertSame(2, $this->calendar->weekOfMonth(Carbon::parse('2026-09-12')));
        $this->assertSame(3, $this->calendar->weekOfMonth(Carbon::parse('2026-09-19')));
        $this->assertSame(4, $this->calendar->weekOfMonth(Carbon::parse('2026-09-26')));
        $this->assertSame(5, $this->calendar->weekOfMonth(Carbon::parse('2026-10-31')));
    }

    public function test_the_first_and_third_saturdays_are_off_and_the_rest_are_worked(): void
    {
        $employee = $this->sixDayEmployee();

        $this->assertFalse($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-05')));
        $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-12')));
        $this->assertFalse($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-19')));
        $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-26')));
    }

    public function test_a_fifth_saturday_is_worked_unless_it_is_named(): void
    {
        $employee = $this->sixDayEmployee();

        // 31 October 2026 is a fifth Saturday.
        $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse('2026-10-31')));
    }

    public function test_second_and_fourth_can_be_the_off_ones_instead(): void
    {
        $employee = $this->sixDayEmployee([2, 4]);

        $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-05')));
        $this->assertFalse($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-12')));
        $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-19')));
        $this->assertFalse($this->calendar->isWorkingDay($employee, Carbon::parse('2026-09-26')));
    }

    public function test_a_branch_that_never_works_saturdays_is_unaffected(): void
    {
        $branch = $this->makeBranch([
            'working_days' => [1, 2, 3, 4, 5],
            'saturday_offs' => [1, 3],
        ]);

        $this->assertSame([], $branch->saturdayOffWeeks());
        $this->assertSame('Saturdays are off', $branch->saturdayLabel());
    }

    public function test_a_six_day_branch_with_nothing_set_works_every_saturday(): void
    {
        $employee = $this->sixDayEmployee([]);

        foreach (['2026-09-05', '2026-09-12', '2026-09-19', '2026-09-26'] as $date) {
            $this->assertTrue($this->calendar->isWorkingDay($employee, Carbon::parse($date)));
        }
    }

    // ------------------------------------------------------- what it feeds

    public function test_an_off_saturday_is_not_counted_as_a_working_day(): void
    {
        $employee = $this->sixDayEmployee();

        // September 2026: 30 days, 4 Sundays, 2 off Saturdays.
        $count = $this->calendar->workingDayCount(
            $employee,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
        );

        $this->assertSame(24, $count);
    }

    public function test_leave_across_an_off_saturday_does_not_spend_a_day_on_it(): void
    {
        $this->sixDayEmployee();
        $employee = Employee::first()->fresh(['branch', 'shift']);
        $type = LeaveType::where('code', 'CL')->firstOrFail();

        // Friday the 18th to Monday the 21st, over the third Saturday.
        $days = app(LeaveService::class)->calculateDays(
            $employee,
            Carbon::parse('2026-09-18'),
            Carbon::parse('2026-09-21'),
            DayType::FullDay,
        );

        // Friday, Monday — the Saturday is off and the Sunday always was.
        $this->assertSame(2.0, $days);
    }

    public function test_leave_across_a_working_saturday_does_spend_it(): void
    {
        $this->sixDayEmployee();
        $employee = Employee::first()->fresh(['branch', 'shift']);

        // Friday the 11th to Monday the 14th, over the second Saturday.
        $days = app(LeaveService::class)->calculateDays(
            $employee,
            Carbon::parse('2026-09-11'),
            Carbon::parse('2026-09-14'),
            DayType::FullDay,
        );

        $this->assertSame(3.0, $days);
    }

    public function test_the_month_reads_an_off_saturday_as_a_weekly_off(): void
    {
        $this->sixDayEmployee();
        $employee = Employee::first()->fresh(['branch', 'shift']);

        $map = $this->calendar->classifyPeriod(
            $employee,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
        );

        $this->assertSame('weekend', $map['2026-09-05']);
        $this->assertSame('working', $map['2026-09-12']);
        $this->assertSame('weekend', $map['2026-09-19']);
    }

    // ------------------------------------------------------- the branch form

    public function test_the_pattern_is_saved_from_the_branch_screen(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('branches.store'), [
            'name' => 'Six Day Office', 'code' => 'SIX',
            'timezone' => 'Asia/Kolkata',
            'work_start_time' => '09:30', 'work_end_time' => '18:30',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'saturday_offs' => [1, 3],
            'status' => 'active',
        ])->assertRedirect();

        $branch = Branch::where('code', 'SIX')->firstOrFail();

        $this->assertSame([1, 3], $branch->saturdayOffWeeks());
        $this->assertSame('1st and 3rd Saturday off, the rest worked', $branch->saturdayLabel());
    }

    public function test_the_pattern_can_be_imported(): void
    {
        $this->seedReferenceData();

        $import = app(DataImportService::class);
        $path = tempnam(sys_get_temp_dir(), 'branch').'.csv';
        file_put_contents($path, "code,name,working_days,saturday_offs\nSIX,Six Day,Mon-Sat,\"1,3\"\n");

        $import->apply($import->check(ImportTypes::BRANCHES, $path, 'branches.csv'));

        $this->assertSame([1, 3], Branch::where('code', 'SIX')->firstOrFail()->saturdayOffWeeks());
    }
}
