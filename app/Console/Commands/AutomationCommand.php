<?php

namespace App\Console\Commands;

use App\Models\AutomationRun;
use App\Support\Automations;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * A scheduled job that records what it did.
 *
 * Subclasses put their work in `work()` and return a sentence describing the
 * outcome. Whether it ran, whether it worked and what it did are then on the
 * automations screen without each command having to remember to say so.
 *
 * A failure is recorded and re-thrown: the run log should show it, and the
 * scheduler and the log should still hear about it.
 */
abstract class AutomationCommand extends Command
{
    /** The catalogue key this command answers to. */
    abstract protected function automationKey(): string;

    /**
     * The work. Return a sentence for the log; set `$this->affected` to the
     * number of records touched, where a number means anything.
     */
    abstract protected function work(): string;

    protected int $affected = 0;

    public function handle(): int
    {
        $key = $this->automationKey();

        if (! Automations::exists($key)) {
            $this->error('There is no automation called '.$key.'.');

            return self::FAILURE;
        }

        // A person pressing Run now is doing so deliberately, so only the
        // scheduler is turned away when the automation is switched off.
        if (! $this->option('forced') && ! Automations::enabled($key)) {
            $this->line(Automations::find($key)['label'].' is switched off.');

            return self::SUCCESS;
        }

        $run = AutomationRun::begin($key, $this->userId());

        try {
            $summary = $this->work();
        } catch (\Throwable $e) {
            $run->fail($e);
            $this->error($e->getMessage());

            throw $e;
        }

        $run->succeed($summary, $this->affected);
        $this->info($summary);

        return self::SUCCESS;
    }

    /** Who pressed Run now, when it was a person. */
    protected function userId(): ?int
    {
        $id = $this->option('as-user');

        return $id === null ? null : (int) $id;
    }

    /**
     * Two options every automation takes.
     *
     * Added here rather than in each signature so no subclass can forget one.
     * Laravel ignores getOptions() once a command declares a $signature, but
     * configure() still runs — from the Symfony constructor, before the
     * signature's own options are added — so both sets end up on the command.
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('forced', null, InputOption::VALUE_NONE,
            'Run even when the automation is switched off');

        $this->addOption('as-user', null, InputOption::VALUE_REQUIRED,
            'The person who asked for this run');
    }
}
