<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Casual Leave', 'code' => 'CL', 'days_per_year' => 12,
                'is_paid' => true, 'allow_half_day' => true, 'carry_forward' => false,
                'max_consecutive_days' => 3, 'min_notice_days' => 1, 'color' => '#2563eb',
                'description' => 'Short notice personal leave for day-to-day needs.',
            ],
            [
                'name' => 'Sick Leave', 'code' => 'SL', 'days_per_year' => 12,
                'is_paid' => true, 'allow_half_day' => true, 'carry_forward' => false,
                'max_consecutive_days' => 0, 'min_notice_days' => 0, 'color' => '#dc2626',
                'requires_attachment' => false,
                'description' => 'Leave for illness. A medical certificate is expected beyond three days.',
            ],
            [
                // Earned a month at a time rather than granted in January:
                // 1.5 days once confirmed, 1 day while on probation, which
                // comes to the same 18 days over a full year.
                'name' => 'Earned Leave', 'code' => 'EL', 'days_per_year' => 18,
                'accrual' => LeaveType::ACCRUAL_MONTHLY,
                'days_per_month' => 1.5,
                'probation_days_per_month' => 1,
                'accrue_in_advance' => false,
                'accrual_starts_on' => date('Y').'-01-01',
                'is_paid' => true, 'allow_half_day' => true, 'carry_forward' => true,
                'max_carry_forward_days' => 30, 'max_consecutive_days' => 15, 'min_notice_days' => 7,
                'color' => '#059669', 'applicable_after_months' => 0,
                'description' => 'Planned annual leave, earned at 1.5 days a month once confirmed '
                    .'and 1 day a month on probation.',
            ],
            [
                'name' => 'Maternity Leave', 'code' => 'ML', 'days_per_year' => 182,
                'is_paid' => true, 'allow_half_day' => false, 'carry_forward' => false,
                'min_notice_days' => 30, 'applicable_gender' => 'female',
                'applicable_after_months' => 6, 'requires_attachment' => true, 'color' => '#db2777',
                'description' => 'Statutory maternity benefit of 26 weeks.',
            ],
            [
                'name' => 'Paternity Leave', 'code' => 'PL', 'days_per_year' => 10,
                'is_paid' => true, 'allow_half_day' => false, 'carry_forward' => false,
                'min_notice_days' => 7, 'applicable_gender' => 'male',
                'applicable_after_months' => 6, 'color' => '#7c3aed',
                'description' => 'Leave for new fathers, to be taken within six months of birth.',
            ],
            [
                'name' => 'Bereavement Leave', 'code' => 'BL', 'days_per_year' => 5,
                'is_paid' => true, 'allow_half_day' => false, 'carry_forward' => false,
                'min_notice_days' => 0, 'color' => '#475569',
                'description' => 'Compassionate leave following the loss of an immediate family member.',
            ],
            [
                'name' => 'Loss of Pay', 'code' => 'LOP', 'days_per_year' => 0,
                'is_paid' => false, 'allow_half_day' => true, 'carry_forward' => false,
                'min_notice_days' => 0, 'color' => '#b45309',
                'description' => 'Unpaid leave taken once the paid balance is exhausted.',
            ],
            [
                'name' => 'Comp Off', 'code' => 'CO', 'days_per_year' => 0,
                'is_paid' => true, 'allow_half_day' => true, 'carry_forward' => false,
                'min_notice_days' => 1, 'color' => '#0891b2',
                'description' => 'Compensatory time off granted for approved weekend or holiday work.',
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type + ['status' => 'active']);
        }
    }
}
