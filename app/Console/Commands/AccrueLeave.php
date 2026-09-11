<?php

namespace App\Console\Commands;

use App\Models\LeaveType;
use App\Services\LeaveAccrualService;
use Illuminate\Support\Carbon;

/**
 * Credit the leave everybody has earned since this last ran.
 *
 * Scheduled daily rather than monthly on purpose: the ledger decides what is
 * owed, so running it every day credits each month exactly once and quietly
 * catches up anything a stopped server missed.
 */
class AccrueLeave extends AutomationCommand
{
    protected $signature = 'hrms:accrue-leave
        {--date= : Reason from this date rather than today (Y-m-d).}
        {--branch= : Only employees at this branch id.}
        {--dry-run : Work out what would be credited without writing it.}';

    protected $description = 'Credit monthly leave to everybody who has earned it';

    protected function automationKey(): string
    {
        return 'leave.accrue';
    }

    protected function work(): string
    {
        $accruals = app(LeaveAccrualService::class);
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $types = LeaveType::active()->get()->filter(fn (LeaveType $type) => $type->accruesMonthly());

        if ($types->isEmpty()) {
            $this->line('  <fg=gray>Set one under Leave Types: choose "Earned monthly" and give the rates.</>');

            return 'No leave type is set to accrue monthly, so there is nothing to credit.';
        }

        $this->newLine();

        foreach ($types as $type) {
            $this->components->twoColumnDetail(
                '<fg=cyan>'.$type->name.'</>',
                $type->entitlementLabel().($type->accrue_in_advance ? ', in advance' : ', in arrears'),
            );
        }

        $result = $accruals->accrue(
            $asOf,
            $this->option('branch') ? (int) $this->option('branch') : null,
            $this->option('dry-run'),
        );

        $this->newLine();

        $message = sprintf(
            '%s %d month%s totalling %s days across %d %s.',
            $this->option('dry-run') ? 'Would credit' : 'Credited',
            $result['credited'],
            $result['credited'] === 1 ? '' : 's',
            rtrim(rtrim(number_format($result['days'], 2), '0'), '.') ?: '0',
            $result['employees'],
            $result['employees'] === 1 ? 'person' : 'people',
        );

        $this->affected = $this->option('dry-run') ? 0 : $result['employees'];

        return $result['credited'] > 0
            ? $message
            : 'Everybody is up to date as at '.$asOf->toDateString().'.';
    }
}
