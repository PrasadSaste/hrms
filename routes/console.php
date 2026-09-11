<?php

use App\Support\Automations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Needs `php artisan schedule:run` every minute from cron — see
| docs/DEPLOYMENT.md. Without it nothing below ever fires.
|
| Nothing is listed here by hand. Every job comes from App\Support\Automations,
| which is also what the automations screen reads, so what an administrator
| sees and what actually runs cannot drift apart. Adding a job there schedules
| it here.
|
| The hour comes from the settings, so it is an administrator's to move. The
| read is defended inside the catalogue, because the scheduler is also loaded
| by `artisan migrate` on an empty database, before there is a settings table.
| Whether a job is switched off is decided when it runs rather than here: the
| schedule is built once at boot, and a job turned off at nine should stop that
| evening, not at the next deployment.
*/

foreach (Automations::all() as $key => $automation) {
    $event = Schedule::command($automation['command'])
        ->timezone(config('app.timezone'))
        ->withoutOverlapping();

    match ($automation['frequency']) {
        Automations::YEARLY => $event->yearlyOn(1, 1, Automations::time($key)),
        Automations::MONTHLY => $event->monthlyOn(1, Automations::time($key)),
        default => $event->dailyAt(Automations::time($key)),
    };
}
