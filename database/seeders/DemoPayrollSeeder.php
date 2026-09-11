<?php

namespace Database\Seeders;

use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Runs payroll for the previous two months so payslips exist on install. */
class DemoPayrollSeeder extends Seeder
{
    public function __construct(protected PayrollService $payroll) {}

    public function run(): void
    {
        $admin = User::role(Roles::SUPER_ADMIN)->first();
        $accountant = User::role(Roles::ACCOUNTANT)->first() ?? $admin;

        if (! $admin) {
            return;
        }

        foreach ([2, 1] as $monthsAgo) {
            $period = Carbon::today()->subMonthsNoOverflow($monthsAgo);

            $existing = Payroll::where('month', (int) $period->format('n'))
                ->where('year', (int) $period->format('Y'))
                ->whereNull('branch_id')
                ->first();

            if ($existing) {
                continue;
            }

            $run = $this->payroll->createRun([
                'month' => (int) $period->format('n'),
                'year' => (int) $period->format('Y'),
                'branch_id' => null,
                'title' => 'Payroll '.$period->format('F Y'),
                'payment_date' => $period->copy()->endOfMonth()->toDateString(),
                'notes' => 'Seeded demo payroll run.',
            ], $accountant);

            $this->payroll->generate($run);
            $this->payroll->approve($run->fresh(), $admin);

            // The older run is also settled so payment states differ.
            if ($monthsAgo === 2) {
                $this->payroll->markPaid(
                    $run->fresh(),
                    'NEFT'.$period->format('Ym').'001',
                    $period->copy()->endOfMonth(),
                );
            }
        }
    }
}
