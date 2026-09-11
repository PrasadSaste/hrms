<?php

namespace App\Services;

use App\Models\DataImport;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Import\RowImporter;
use App\Support\Csv;
use App\Support\ImportTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Loading an old system's data from a spreadsheet.
 *
 * Two steps, always in this order. `check()` reads the file and reports every
 * row it cannot accept, having written nothing. `apply()` writes, inside one
 * transaction, and only what `check()` already approved.
 *
 * The reason for the ceremony is that this is used exactly once per
 * organisation, on the day they leave their old HRMS, with data nobody can
 * easily re-derive. An import that half-works is worse than one that refuses.
 */
class DataImportService
{
    /** How many rows a file may hold before we ask for the command line. */
    public const MAX_ROWS = 20_000;

    /**
     * Read a file, check every row, and record the result.
     *
     * Nothing is written to the application's own tables here.
     */
    public function check(string $type, string $path, string $filename, ?User $user = null): DataImport
    {
        $this->assertType($type);

        $importer = $this->importer($type);
        $parsed = Csv::read($path);

        $this->assertColumns($type, $parsed['headers'], $importer->aliases());

        if (count($parsed['rows']) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => 'This file holds '.number_format(count($parsed['rows'])).' rows. '
                    .'Files above '.number_format(self::MAX_ROWS).' rows are better split up, or '
                    .'loaded on the server with "php artisan hrms:import".',
            ]);
        }

        $errors = [];
        $valid = 0;

        $importer->startPass();

        foreach ($parsed['rows'] as $row) {
            $problems = $importer->check($row['values'], $row['line']);

            if ($problems === []) {
                $valid++;

                continue;
            }

            $errors[] = [
                'line' => $row['line'],
                'problems' => $problems,
                'values' => $row['values'],
            ];
        }

        return DataImport::create([
            'type' => $type,
            'original_filename' => $filename,
            'stored_path' => $path,
            'status' => DataImport::STATUS_CHECKED,
            'rows_total' => count($parsed['rows']),
            'rows_valid' => $valid,
            'rows_invalid' => count($errors),
            // The whole file's worth of problems is kept, but a runaway file
            // should not put a megabyte of JSON in one column.
            'errors' => array_slice($errors, 0, 500),
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Write the rows that passed the check.
     *
     * Everything happens in one transaction: if a row fails while being
     * written — something the check could not have known, like a database
     * constraint — the whole file is rolled back and nothing is left half
     * loaded.
     */
    public function apply(DataImport $import, bool $skipInvalid = true): DataImport
    {
        $this->assertType($import->type);

        if ($import->isImported()) {
            throw ValidationException::withMessages([
                'import' => 'This file has already been imported.',
            ]);
        }

        if ($import->rows_invalid > 0 && ! $skipInvalid) {
            throw ValidationException::withMessages([
                'import' => 'There are '.$import->rows_invalid.' rows with problems. Fix them and '
                    .'upload again, or choose to import the rows that are correct and leave the rest.',
            ]);
        }

        $importer = $this->importer($import->type);
        $parsed = Csv::read($import->stored_path);

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        try {
            DB::transaction(function () use ($parsed, $importer, &$counts) {
                $importer->startPass();

                foreach ($parsed['rows'] as $row) {
                    if ($importer->check($row['values'], $row['line']) !== []) {
                        $counts['skipped']++;

                        continue;
                    }

                    $outcome = $importer->apply($row['values']);
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                }

                // Reporting lines and anything else that needed the whole file.
                $importer->finish();
            });
        } catch (\Throwable $e) {
            Log::error('HRMS import failed', [
                'import_id' => $import->id,
                'type' => $import->type,
                'error' => $e->getMessage(),
            ]);

            $import->update([
                'status' => DataImport::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'import' => 'The import was stopped and nothing was saved: '.$e->getMessage(),
            ]);
        }

        $import->update([
            'status' => DataImport::STATUS_IMPORTED,
            'rows_created' => $counts['created'],
            'rows_updated' => $counts['updated'],
            'rows_skipped' => $counts['skipped'],
            'imported_at' => now(),
        ]);

        return $import->fresh();
    }

    /** The template for a type: the headers, and one example row to copy. */
    public function template(string $type): string
    {
        $this->assertType($type);

        $columns = ImportTypes::columns($type);
        $example = ImportTypes::exampleRow($type);

        if ($type === ImportTypes::SALARY_STRUCTURES) {
            foreach ($this->salaryComponentColumns() as $column) {
                $columns[] = $column;
                $example[$column] = '';
            }
        }

        return Csv::write($columns, [collect($columns)->map(fn ($c) => $example[$c] ?? '')->all()]);
    }

    /** The rows that failed, with a column saying why, ready to fix and re-upload. */
    public function errorReport(DataImport $import): string
    {
        $columns = ImportTypes::columns($import->type);
        $rows = [];

        foreach ($import->errors ?? [] as $error) {
            $row = collect($columns)->map(fn (string $c) => $error['values'][$c] ?? '')->all();
            array_unshift($row, implode(' ', $error['problems']));
            array_unshift($row, $error['line']);
            $rows[] = $row;
        }

        return Csv::write(array_merge(['row', 'problem'], $columns), $rows);
    }

    /** The first rows of a file, for the preview on screen. */
    public function preview(DataImport $import, int $limit = 10): array
    {
        return Csv::read($import->stored_path, $limit);
    }

    /** Component columns are added to the salary template from what exists. */
    public function salaryComponentColumns(): array
    {
        return SalaryComponent::query()
            ->where('status', 'active')
            ->orderBy('sequence')
            ->pluck('code')
            ->map(fn (string $code) => 'component:'.strtolower($code))
            ->all();
    }

    public function importer(string $type): RowImporter
    {
        $this->assertType($type);

        return app(ImportTypes::find($type)['importer']);
    }

    /** Store an uploaded file where the import can read it twice. */
    public function store(UploadedFile $file): string
    {
        $path = $file->store('imports');

        return Storage::disk('local')->path($path);
    }

    protected function assertType(string $type): void
    {
        if (! ImportTypes::exists($type)) {
            throw ValidationException::withMessages(['type' => 'There is nothing to import called "'.$type.'".']);
        }
    }

    /**
     * A file missing a required column is refused whole, rather than reported
     * as every row being wrong in the same way.
     */
    protected function assertColumns(string $type, array $headers, array $aliases = []): void
    {
        if ($headers === []) {
            throw ValidationException::withMessages([
                'file' => 'That file has no readable header row. The first line must name the columns.',
            ]);
        }

        // A column the old system spelled differently still counts as present.
        $missing = collect(ImportTypes::requiredColumns($type))
            ->reject(fn (string $column) => in_array($column, $headers, true)
                || array_intersect($aliases[$column] ?? [], $headers) !== [])
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'The file is missing '.(count($missing) === 1 ? 'a column' : 'columns')
                    .' it needs: '.implode(', ', $missing).'. Download the template to see the '
                    .'expected header row.',
            ]);
        }
    }
}
