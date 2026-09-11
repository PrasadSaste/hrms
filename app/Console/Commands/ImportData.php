<?php

namespace App\Console\Commands;

use App\Models\DataImport;
use App\Models\User;
use App\Services\DataImportService;
use App\Support\ImportTypes;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * The same import as the screen, for files too big to push through a browser.
 *
 * It checks by default and writes only when told to, which is the same two
 * steps in the same order — just with the confirmation being a flag rather
 * than a button.
 */
class ImportData extends Command
{
    protected $signature = 'hrms:import
        {type? : What is in the file — run without it to list the choices}
        {file? : Path to the CSV}
        {--commit : Actually write. Without this the file is only checked.}
        {--strict : Refuse the whole file if any row has a problem.}
        {--errors= : Write the rows that failed to this file, with the reason.}
        {--user= : Email of the person to record the import against.}';

    protected $description = 'Load branches, employees, leave balances, pay or attendance from a CSV';

    public function handle(DataImportService $imports): int
    {
        $type = $this->argument('type');

        if (! $type) {
            return $this->listTypes();
        }

        if (! ImportTypes::exists($type)) {
            $this->components->error('There is nothing to import called "'.$type.'".');

            return $this->listTypes(self::FAILURE);
        }

        $file = $this->argument('file');

        if (! $file || ! is_readable($file)) {
            $this->components->error('Give the path to a readable CSV file.');

            return self::FAILURE;
        }

        try {
            $import = $imports->check(
                $type,
                realpath($file),
                basename($file),
                $this->option('user') ? User::where('email', $this->option('user'))->first() : null,
            );
        } catch (ValidationException $e) {
            foreach (collect($e->errors())->flatten() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->report($import);

        if ($this->option('errors') && $import->hasErrors()) {
            file_put_contents($this->option('errors'), $imports->errorReport($import));
            $this->components->info('The rows to fix are in '.$this->option('errors').'.');
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->components->warn('Checked only — nothing was written. Add --commit to import.');

            return $import->hasErrors() ? self::FAILURE : self::SUCCESS;
        }

        try {
            $import = $imports->apply($import, ! $this->option('strict'));
        } catch (ValidationException $e) {
            foreach (collect($e->errors())->flatten() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%s imported: %d added, %d updated, %d skipped.',
            $import->typeLabel(),
            $import->rows_created,
            $import->rows_updated,
            $import->rows_skipped,
        ));

        return self::SUCCESS;
    }

    protected function report(DataImport $import): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>File</>', $import->original_filename);
        $this->components->twoColumnDetail('<fg=gray>Rows</>', (string) $import->rows_total);
        $this->components->twoColumnDetail('<fg=gray>Ready</>', (string) $import->rows_valid);
        $this->components->twoColumnDetail('<fg=gray>With problems</>', (string) $import->rows_invalid);

        if (! $import->hasErrors()) {
            return;
        }

        $this->newLine();

        // Enough to act on without burying the summary under a thousand lines.
        foreach (array_slice($import->errors, 0, 20) as $error) {
            $this->components->twoColumnDetail(
                '<fg=red>Row '.$error['line'].'</>',
                implode(' ', $error['problems']),
            );
        }

        if ($import->rows_invalid > 20) {
            $this->components->warn(($import->rows_invalid - 20).' more rows have problems. '
                .'Use --errors=rows.csv to write them all out.');
        }
    }

    protected function listTypes(int $exit = self::SUCCESS): int
    {
        $this->newLine();
        $this->components->info('Load these in order — each one points at the ones above it:');

        foreach (ImportTypes::all() as $key => $type) {
            $this->components->twoColumnDetail('<fg=cyan>'.$key.'</>', $type['summary']);
        }

        $this->newLine();
        $this->line('  <fg=gray>php artisan hrms:import employees people.csv</>');
        $this->line('  <fg=gray>php artisan hrms:import employees people.csv --commit</>');
        $this->newLine();

        return $exit;
    }
}
