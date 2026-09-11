<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollStatus;
use App\Models\SalaryComponent;
use PHPUnit\Framework\TestCase;

class SalaryComponentTest extends TestCase
{
    protected function component(array $attributes = []): SalaryComponent
    {
        return new SalaryComponent(array_merge([
            'name' => 'Test',
            'code' => 'TEST',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => 0,
        ], $attributes));
    }

    public function test_a_fixed_component_returns_its_value_unchanged(): void
    {
        $component = $this->component();

        $this->assertSame(
            1600.0,
            $component->resolveAmount(1600, 'fixed', ['basic' => 40000, 'gross' => 80000, 'ctc' => 100000]),
        );
    }

    public function test_a_percentage_of_basic_is_resolved(): void
    {
        $component = $this->component(['percentage_of' => 'basic']);

        $this->assertSame(
            20000.0,
            $component->resolveAmount(50, 'percentage', ['basic' => 40000, 'gross' => 80000, 'ctc' => 100000]),
        );
    }

    public function test_a_percentage_of_gross_is_resolved(): void
    {
        $component = $this->component(['percentage_of' => 'gross']);

        $this->assertSame(
            4000.0,
            $component->resolveAmount(5, 'percentage', ['basic' => 40000, 'gross' => 80000, 'ctc' => 100000]),
        );
    }

    public function test_a_percentage_of_monthly_ctc_is_resolved(): void
    {
        $component = $this->component(['percentage_of' => 'ctc']);

        $this->assertSame(
            40000.0,
            $component->resolveAmount(40, 'percentage', ['basic' => 40000, 'gross' => 80000, 'ctc' => 100000]),
        );
    }

    public function test_a_percentage_defaults_to_basic_when_no_base_is_set(): void
    {
        $component = $this->component();

        $this->assertSame(
            4800.0,
            $component->resolveAmount(12, 'percentage', ['basic' => 40000, 'gross' => 80000, 'ctc' => 100000]),
        );
    }

    public function test_attendance_statuses_report_whether_they_are_payable(): void
    {
        $this->assertTrue(AttendanceStatus::Present->isPayable());
        $this->assertTrue(AttendanceStatus::Late->isPayable());
        $this->assertTrue(AttendanceStatus::Holiday->isPayable());
        $this->assertFalse(AttendanceStatus::Absent->isPayable());
        $this->assertFalse(AttendanceStatus::HalfDay->isPayable());
    }

    public function test_a_half_day_costs_half_and_a_full_day_costs_one(): void
    {
        $this->assertSame(1.0, DayType::FullDay->factor());
        $this->assertSame(0.5, DayType::FirstHalf->factor());
        $this->assertSame(0.5, DayType::SecondHalf->factor());
    }

    public function test_employment_statuses_report_whether_the_person_is_on_roll(): void
    {
        $this->assertTrue(EmploymentStatus::Permanent->isOnRoll());
        $this->assertTrue(EmploymentStatus::Probation->isOnRoll());
        $this->assertTrue(EmploymentStatus::NoticePeriod->isOnRoll());
        $this->assertFalse(EmploymentStatus::Resigned->isOnRoll());
        $this->assertFalse(EmploymentStatus::Terminated->isOnRoll());
    }

    public function test_only_a_draft_or_pending_payroll_run_is_editable(): void
    {
        $this->assertTrue(PayrollStatus::Draft->isEditable());
        $this->assertTrue(PayrollStatus::PendingApproval->isEditable());
        $this->assertFalse(PayrollStatus::Approved->isEditable());
        $this->assertFalse(PayrollStatus::Paid->isEditable());
    }

    public function test_enum_labels_are_human_readable(): void
    {
        $this->assertSame('Half Day', AttendanceStatus::HalfDay->label());
        $this->assertSame('Pending Approval', PayrollStatus::PendingApproval->label());
        $this->assertSame('Notice Period', EmploymentStatus::NoticePeriod->label());
    }
}
