<?php

namespace App\Console\Commands;

use App\Services\LeaveService;
use Illuminate\Support\Carbon;

/**
 * Open the leave year.
 *
 * `LeaveService::allocateYear()` has always done this correctly — everybody's
 * entitlement, and last year's remaining days carried forward up to whatever
 * the type allows. It was only ever called by a person pressing a button on
 * the Leave Allocations screen, so a year nobody remembered to open was a year
 * in which nobody could apply for leave and last year's balance quietly
 * expired.
 *
 * Safe to run more than once: allocating an existing year updates it rather
 * than adding to it, and a monthly type's running total is left alone.
 */
class AllocateLeaveYear extends AutomationCommand
{
    protected $signature = 'hrms:allocate-leave-year
        {--year= : Open this year rather than the current one.}
        {--branch= : Only employees at this branch id.}';

    protected $description = 'Give everybody their leave allocation for the year';

    public function __construct(protected LeaveService $leave)
    {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'leave.allocate-year';
    }

    protected function work(): string
    {
        $year = (int) ($this->option('year') ?: Carbon::today()->year);
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;

        $count = $this->leave->allocateYear($year, $branch);
        $this->affected = $count;

        // One allocation per person per leave type, so this counts rows
        // rather than people — saying "people" would overstate it sevenfold.
        return $count === 0
            ? 'Nobody needed an allocation for '.$year.'.'
            : 'Wrote '.$count.' '.str('allocation')->plural($count).' for '.$year.'.';
    }
}
