<?php

namespace Database\Seeders;

use App\Enums\ComponentType;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // Earnings
            [
                'name' => 'Basic Salary', 'code' => 'BASIC', 'type' => ComponentType::Earning->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'ctc', 'default_value' => 40,
                'sequence' => 1, 'is_taxable' => true, 'affects_gross' => true, 'prorate_on_lop' => true,
                'description' => '40% of monthly CTC, the base for most statutory calculations.',
            ],
            [
                'name' => 'House Rent Allowance', 'code' => 'HRA', 'type' => ComponentType::Earning->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'basic', 'default_value' => 50,
                'sequence' => 2, 'is_taxable' => true, 'affects_gross' => true, 'prorate_on_lop' => true,
                'description' => '50% of basic salary.',
            ],
            [
                'name' => 'Conveyance Allowance', 'code' => 'CONV', 'type' => ComponentType::Earning->value,
                'calculation_type' => 'fixed', 'default_value' => 1600,
                'sequence' => 3, 'is_taxable' => false, 'affects_gross' => true, 'prorate_on_lop' => true,
            ],
            [
                'name' => 'Medical Allowance', 'code' => 'MED', 'type' => ComponentType::Earning->value,
                'calculation_type' => 'fixed', 'default_value' => 1250,
                'sequence' => 4, 'is_taxable' => false, 'affects_gross' => true, 'prorate_on_lop' => true,
            ],
            [
                'name' => 'Special Allowance', 'code' => 'SPL', 'type' => ComponentType::Earning->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'ctc', 'default_value' => 20,
                'sequence' => 5, 'is_taxable' => true, 'affects_gross' => true, 'prorate_on_lop' => true,
                'description' => 'Balancing component that brings gross up to the agreed CTC.',
            ],

            // Deductions
            [
                'name' => 'Provident Fund', 'code' => 'PF', 'type' => ComponentType::Deduction->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'basic', 'default_value' => 12,
                // 12% of a basic capped at the statutory wage, not of the whole
                // basic: somebody on 80,000 contributes the same as somebody on
                // the cap.
                'wage_ceiling' => 15000,
                'sequence' => 10, 'is_statutory' => true, 'affects_gross' => false, 'prorate_on_lop' => true,
                'description' => 'Employee contribution at 12% of basic, on a wage capped at ₹15,000.',
                'statutory_note' => 'Wage ceiling ₹15,000 a month. Check it against the current rules before a run.',
            ],
            [
                'name' => 'Professional Tax', 'code' => 'PT', 'type' => ComponentType::Deduction->value,
                // A table, not a percentage, and the table is the state's.
                'calculation_type' => 'slab', 'percentage_of' => 'gross', 'default_value' => 0,
                'slabs' => [
                    ['up_to' => 24999, 'amount' => 0],
                    ['up_to' => null, 'amount' => 200],
                ],
                'sequence' => 11, 'is_statutory' => true, 'affects_gross' => false, 'prorate_on_lop' => false,
                'description' => 'Charged on monthly gross, by slab.',
                'statutory_note' => 'Karnataka slabs are loaded as an example. Professional tax is set '
                    .'by each state — check yours and edit the slabs before running payroll.',
            ],
            [
                'name' => 'Income Tax (TDS)', 'code' => 'TDS', 'type' => ComponentType::Deduction->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'gross', 'default_value' => 5,
                'sequence' => 12, 'is_statutory' => true, 'affects_gross' => false, 'prorate_on_lop' => false,
                'description' => 'Tax deducted at source as a flat share of gross, adjustable per '
                    .'employee. Switch on "Deduct income tax from salaries" under Settings → Payroll '
                    .'and this is replaced on every slip by the real computation — the year projected, '
                    .'the declared investments applied, and what is left spread over the months that remain.',
            ],
            [
                'name' => 'Employee State Insurance', 'code' => 'ESI', 'type' => ComponentType::Deduction->value,
                'calculation_type' => 'percentage', 'percentage_of' => 'gross', 'default_value' => 0.75,
                // Not a capped wage: above the ceiling somebody is outside the
                // scheme altogether and pays nothing.
                'eligibility_ceiling' => 21000,
                'sequence' => 13, 'is_statutory' => true, 'affects_gross' => false, 'prorate_on_lop' => false,
                'status' => 'inactive',
                'description' => 'Employee contribution at 0.75% of gross, for anybody below the ceiling.',
                'statutory_note' => 'Applies only where monthly gross is ₹21,000 or less. Switched off '
                    .'by default — turn it on if your establishment is covered.',
            ],
        ];

        foreach ($components as $component) {
            SalaryComponent::updateOrCreate(
                ['code' => $component['code']],
                $component + ['status' => 'active'],
            );
        }
    }
}
