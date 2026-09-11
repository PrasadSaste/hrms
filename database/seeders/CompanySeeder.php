<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Setting;
use App\Models\Signatory;
use Illuminate\Database\Seeder;

/**
 * The group's payroll entities.
 *
 * Only names and codes are seeded. Registration and statutory numbers differ
 * per entity and are nobody's guess to make, so they are left for whoever knows
 * them to fill in on the company screen.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $first = Company::updateOrCreate(
            ['code' => 'BSPL'],
            [
                'name' => 'Beyond Sure',
                'legal_name' => 'Beyondsure Private Limited',
                'email' => Setting::get('company_email'),
                'phone' => Setting::get('company_phone'),
                'website' => Setting::get('company_website'),
                // Split across the fields rather than crammed into line one,
                // so the letterhead prints it as a block.
                'address_line1' => 'Level 6, Prestige Tower',
                'address_line2' => 'Outer Ring Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'postal_code' => '560103',
                'country' => 'India',
                'tax_id' => Setting::get('company_tax_id'),
                'logo_path' => Setting::get('company_logo'),
                'currency' => Setting::get('currency', 'INR'),
                'payslip_prefix' => 'BSPL',
                // The pad every salary slip and letter this entity issues is
                // printed on. The watermark falls back to the short name.
                'letterhead_footer' => 'Registered office: Level 6, Prestige Tower, Outer Ring Road, Bengaluru 560103',
                'watermark_enabled' => true,
                'status' => 'active',
                'is_default' => true,
            ],
        );

        // Somebody has to sign. Two per entity, so the screen shows what a
        // second signatory looks like and a letter can be issued over either.
        $this->signatories($first, [
            ['Aarav Mehta', 'Managing Director', true],
            ['Priya Raghavan', 'Head of Human Resources', false],
        ]);

        $second = Company::updateOrCreate(
            ['code' => 'SIBL'],
            [
                'name' => 'Shrigoda Insurance Brokers',
                'legal_name' => 'Shrigoda Insurance Brokers Limited',
                'address_line1' => '4th Floor, Maker Chambers IV',
                'address_line2' => 'Nariman Point',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'postal_code' => '400021',
                'country' => 'India',
                'currency' => Setting::get('currency', 'INR'),
                'payslip_prefix' => 'SIBL',
                'letterhead_footer' => 'Registered office: 4th Floor, Nariman Point, Mumbai 400021',
                'watermark_enabled' => true,
                'status' => 'active',
                'is_default' => false,
            ],
        );

        $this->signatories($second, [
            ['Ravi Menon', 'Director', true],
        ]);

        // The company created by the migration from the old single-company
        // settings is folded into the first real entity, so an installation
        // does not end up with a duplicate placeholder.
        $first->absorbPlaceholder();
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: bool}>  $people
     */
    protected function signatories(Company $company, array $people): void
    {
        foreach ($people as $order => [$name, $designation, $isDefault]) {
            Signatory::updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                [
                    'designation' => $designation,
                    'is_default' => $isDefault,
                    'sort_order' => $order,
                    'status' => 'active',
                ],
            );
        }
    }
}
