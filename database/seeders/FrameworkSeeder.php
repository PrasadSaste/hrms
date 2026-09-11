<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * What every installation needs, whoever it belongs to.
 *
 * Roles and their permissions, the default settings, the leave types, the
 * salary components and a guide for every screen: none of it names a company,
 * so the setup wizard can run it against a database it knows nothing else
 * about and then write the organisation's own answers over the top.
 *
 * DatabaseSeeder calls this first and then adds the Beyond Sure entities and
 * demo workforce; the installer calls this and stops.
 */
class FrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            LeaveTypeSeeder::class,
            SalaryComponentSeeder::class,
            HelpArticleSeeder::class,
        ]);
    }
}
