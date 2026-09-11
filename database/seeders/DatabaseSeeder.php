<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Everything an installation needs whoever it belongs to. The
            // setup wizard runs this much and no more.
            FrameworkSeeder::class,
            // From here down is Beyond Sure's own: its payroll entities, its
            // branches and its calendar.
            CompanySeeder::class,
            OrganisationSeeder::class,
        ]);

        $this->createAdministrator();

        if (app()->environment(['local', 'development', 'testing', 'staging'])
            || env('SEED_DEMO_DATA', false)) {
            $this->call([
                DemoEmployeeSeeder::class,
                DemoActivitySeeder::class,
                DemoPayrollSeeder::class,
            ]);
        }

        $this->command?->newLine();
        $this->command?->info('HRMS seeding complete.');
        $this->command?->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin', 'admin@beyondsure.example', 'Password123!'],
                ['HR Manager', 'priya.raghavan@beyondsure.example', 'Password123!'],
                ['Accountant', 'vikram.desai@beyondsure.example', 'Password123!'],
                ['Branch Manager', 'ananya.iyer@beyondsure.example', 'Password123!'],
                ['Employee', 'arjun.sharma@beyondsure.example', 'Password123!'],
            ],
        );
    }

    /** The always-present administrator account, independent of demo data. */
    protected function createAdministrator(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@beyondsure.example'],
            [
                'name' => 'System Administrator',
                'password' => 'Password123!',
                'status' => 'active',
                'must_change_password' => false,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([Roles::SUPER_ADMIN]);
    }
}
