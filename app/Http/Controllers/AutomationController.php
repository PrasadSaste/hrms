<?php

namespace App\Http\Controllers;

use App\Models\AutomationRun;
use App\Models\Setting;
use App\Support\Automations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * What the system does on its own.
 *
 * A scheduled job used to exist only in the routes file, where nobody running
 * the system could see it. This screen answers the three questions somebody
 * actually has — what runs, when did it last run, and did it work — and lets
 * an administrator move the hour, switch one off, or run one now.
 */
class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('automations.manage'), 403);

        // The last run of each, in one query rather than one per automation.
        $latest = AutomationRun::query()
            ->whereIn('key', Automations::keys())
            ->whereIn('id', function ($query) {
                $query->selectRaw('max(id)')->from('automation_runs')->groupBy('key');
            })
            ->with('trigger')
            ->get()
            ->keyBy('key');

        return view('automations.index', [
            'grouped' => Automations::grouped(),
            'latest' => $latest,
            'history' => AutomationRun::with('trigger')->latest('id')->limit(20)->get(),
            'schedulerSeen' => AutomationRun::query()->whereNull('triggered_by')->exists(),
        ]);
    }

    /** Move the hour, or switch one off. */
    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()->can('automations.manage'), 403);
        abort_unless(Automations::exists($key), 404);

        $validated = $request->validate([
            'time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'enabled' => ['nullable', 'boolean'],
        ], [
            'time.regex' => 'Give the time as HH:MM on the 24-hour clock.',
        ]);

        $definition = Automations::find($key);

        // The one job that had its own setting before this catalogue existed
        // keeps writing to it, so an installation that already moved that hour
        // does not silently go back to the default.
        Setting::put(
            $definition['time_setting'] ?? Automations::settingKey($key, 'time'),
            $validated['time'],
            'automation',
        );

        Setting::put(Automations::settingKey($key, 'enabled'), $request->boolean('enabled'), 'automation');

        return back()->with('success', $definition['label'].' saved. The new hour applies from the next deployment or restart of the scheduler.');
    }

    /**
     * Run one now.
     *
     * Forced, because somebody pressing the button has decided, and queued to
     * the same process rather than the queue so the outcome can be reported
     * back on this request rather than guessed at.
     */
    public function run(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()->can('automations.manage'), 403);
        abort_unless(Automations::exists($key), 404);

        $definition = Automations::find($key);

        try {
            Artisan::call($definition['command'], [
                '--forced' => true,
                '--as-user' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $definition['label'].' did not finish: '.$e->getMessage());
        }

        $run = AutomationRun::for($key)->latest('id')->first();

        return back()->with('success', $definition['label'].' ran. '.($run?->summary ?? ''));
    }
}
