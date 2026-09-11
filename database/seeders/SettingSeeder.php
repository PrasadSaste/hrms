<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\BrandPalette;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['company_name', 'Beyond Sure', 'company', 'string'],
            ['company_email', 'hr@beyondsure.in', 'company', 'string'],
            ['company_phone', '+91 80 4000 1200', 'company', 'string'],
            ['company_website', 'https://www.beyondsure.in', 'company', 'string'],
            ['company_address', "Level 6, Prestige Tower\nOuter Ring Road, Bengaluru 560103\nKarnataka, India", 'company', 'string'],
            ['company_tax_id', 'AABCB1234C', 'company', 'string'],
            ['company_logo', null, 'branding', 'string'],
            ['company_favicon', null, 'branding', 'string'],
            ['brand_color', BrandPalette::DEFAULT, 'branding', 'string'],
            ['brand_secondary_color', BrandPalette::DEFAULT_SECONDARY, 'branding', 'string'],
            ['brand_tertiary_color', BrandPalette::DEFAULT_TERTIARY, 'branding', 'string'],
            ['theme_surface', BrandPalette::DEFAULT_SURFACE, 'branding', 'string'],

            ['currency', 'INR', 'general', 'string'],
            ['timezone', 'Asia/Kolkata', 'general', 'string'],
            ['date_format', 'd M Y', 'general', 'string'],
            ['employee_code_prefix', 'EMP', 'general', 'string'],
            ['employee_code_padding', '4', 'general', 'integer'],
            ['financial_year_start_month', '4', 'general', 'integer'],

            // Available to everybody, required of nobody until an
            // administrator names the roles that must enrol.
            ['security_two_factor_required_roles', '', 'security', 'string'],

            ['mail_from_address', 'hrms@beyondsure.in', 'mail', 'string'],
            ['mail_from_name', 'Beyond Sure HRMS', 'mail', 'string'],
            ['email_notifications_enabled', '1', 'mail', 'boolean'],

            ['attendance_allow_self_punch', '1', 'attendance', 'boolean'],
            ['attendance_auto_absent', '1', 'attendance', 'boolean'],
            ['attendance_require_location', '1', 'attendance', 'boolean'],

            // Off until somebody asks for it: a punch-out the software wrote
            // is a guess, and a guess should be a deliberate choice.
            ['attendance_auto_close_punches', '0', 'attendance', 'boolean'],

            // 200 m covers a building and its car park without flagging
            // somebody who punched in from the far side of the campus.
            ['attendance_geofence_enabled', '1', 'attendance', 'boolean'],
            ['attendance_geofence_radius', '200', 'attendance', 'integer'],
            ['attendance_geofence_alert_recipients', '', 'attendance', 'string'],
            ['attendance_geofence_alert_managers', '0', 'attendance', 'boolean'],
            ['attendance_geofence_daily_report', '1', 'attendance', 'boolean'],
            ['attendance_geofence_report_time', '19:30', 'attendance', 'string'],

            // Overtime is off unless somebody turns it on. Left alone, the
            // minutes attendance already records are reported and not paid,
            // which is what most salaried offices want.
            ['payroll_overtime_enabled', '0', 'payroll', 'boolean'],
            ['payroll_overtime_basis', 'basic', 'payroll', 'string'],
            ['payroll_overtime_multiplier', '2', 'payroll', 'string'],
            ['payroll_overtime_hours_per_day', '8', 'payroll', 'string'],
            ['payroll_overtime_monthly_cap_hours', '0', 'payroll', 'string'],

            // Tax deducted at source is off until somebody asks for it.
            // Taking money out of a salary because the software decided to is
            // a worse failure than not taking it — the second is noticed in
            // April and corrected, the first is noticed on payday.
            ['payroll_tds_enabled', '0', 'payroll', 'boolean'],

            // A month's pay divided by twenty-six days is the usual reading of
            // the Payment of Gratuity Act, and the usual basis for encashment
            // and notice recovery too. The gratuity ceiling is raised by
            // notification from time to time, so it is a setting.
            ['settlement_per_day_divisor', '26', 'payroll', 'integer'],
            ['settlement_gratuity_cap', '2000000', 'payroll', 'string'],
        ];

        foreach ($defaults as [$key, $value, $group, $type]) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'group' => $group,
                'type' => $type,
            ]);
        }
    }
}
