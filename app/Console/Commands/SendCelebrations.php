<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\GeofenceService;
use App\Services\NotificationDispatcher;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The morning note about who is celebrating today.
 *
 * Birthdays were already worked out for the dashboard and the mobile app, and
 * then dropped: somebody had to be looking at the right screen on the right
 * day. Work anniversaries were not counted at all.
 *
 * A birthday on 29 February is treated as falling on 28 February in a year
 * that has no 29th, so nobody is skipped three years in four.
 */
class SendCelebrations extends AutomationCommand
{
    protected $signature = 'hrms:celebrations
        {--date= : Report for this date rather than today (Y-m-d).}
        {--dry-run : Show what would be sent without sending it.}';

    protected $description = 'Tell people about today’s birthdays and work anniversaries';

    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected GeofenceService $recipients,
    ) {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'people.celebrations';
    }

    protected function work(): string
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $people = Employee::query()->active()->onRoll()->with('designation')->get();

        $birthdays = $people->filter(fn (Employee $e) => $this->fallsToday($e->date_of_birth, $today));

        $anniversaries = $people
            ->filter(fn (Employee $e) => $this->fallsToday($e->date_of_joining, $today))
            // Their first day is not an anniversary.
            ->filter(fn (Employee $e) => $e->date_of_joining->year < $today->year);

        if ($birthdays->isEmpty() && $anniversaries->isEmpty()) {
            return 'Nobody is celebrating today.';
        }

        $this->affected = $birthdays->count() + $anniversaries->count();

        $data = [
            'today' => $today->format('l, d F Y'),
            'birthdays' => $this->listOf($birthdays, fn (Employee $e) => '- '.$e->full_name
                .($e->designation ? ' — '.$e->designation->name : '')),
            'anniversaries' => $this->listOf($anniversaries, function (Employee $e) use ($today) {
                $years = $today->year - $e->date_of_joining->year;

                return '- '.$e->full_name.' — '.$years.' '.str('year')->plural($years).' today';
            }),
            'url' => route('employees.index'),
        ];

        $this->line($data['birthdays']);
        $this->line($data['anniversaries']);

        if ($this->option('dry-run')) {
            $this->affected = 0;

            return 'Would have told people about '.($birthdays->count() + $anniversaries->count()).' of them.';
        }

        $sent = 0;

        foreach ($this->recipients->recipients() as $recipient) {
            if ($this->dispatcher->toAddress(NotificationEvents::CELEBRATIONS_TODAY, $recipient['email'], $data)) {
                $sent++;
            }
        }

        return sprintf(
            '%d %s and %d %s, sent to %d %s.',
            $birthdays->count(),
            str('birthday')->plural($birthdays->count()),
            $anniversaries->count(),
            str('anniversary')->plural($anniversaries->count()),
            $sent,
            str('person')->plural($sent),
        );
    }

    /**
     * Whether this date's day and month are today's.
     *
     * A 29 February date falls on the 28th in a year that has no 29th, so the
     * person is not skipped three years in four.
     */
    protected function fallsToday(?Carbon $date, Carbon $today): bool
    {
        if (! $date) {
            return false;
        }

        if ($date->month === $today->month && $date->day === $today->day) {
            return true;
        }

        return $date->month === 2
            && $date->day === 29
            && $today->month === 2
            && $today->day === 28
            && ! $today->isLeapYear();
    }

    protected function listOf(Collection $people, callable $line): string
    {
        return $people->isEmpty()
            ? '_Nobody today._'
            : $people->sortBy('first_name')->map($line)->implode("\n");
    }
}
