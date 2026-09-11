<?php

namespace App\Services\Import;

use App\Support\ImportTypes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What every importer has in common.
 *
 * A row makes two journeys. First `check()` reads it and says what is wrong
 * with it, touching nothing. Then, only if somebody looked at that answer and
 * pressed the button, `apply()` writes it. Keeping those apart is the whole
 * point: a migration is the one moment where a half-finished import is
 * expensive to unpick.
 */
abstract class RowImporter
{
    /** Cache of things looked up by code, so a 500-row file is not 500 queries. */
    protected array $lookups = [];

    /** Values already seen in this pass, for the columns that must be unique. */
    protected array $seen = [];

    abstract public function type(): string;

    /**
     * The validation rules for one row.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Write one checked row.
     *
     * @return string created, updated or skipped
     */
    abstract public function apply(array $row): string;

    /**
     * Check a row without writing anything.
     *
     * @param  int  $line  the line it came from, so a clash can name the other row
     * @return array<int, string> the problems with it, empty when it is fine
     */
    public function check(array $row, int $line = 0): array
    {
        $prepared = $this->prepare($row);

        $validator = Validator::make($prepared, $this->rules(), $this->messages(), $this->attributes());

        if ($validator->fails()) {
            return collect($validator->errors()->all())->unique()->values()->all();
        }

        if ($clash = $this->clashWithinFile($prepared, $line)) {
            return [$clash];
        }

        try {
            $this->checkReferences($prepared);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->unique()->values()->all();
        }

        return [];
    }

    /**
     * Values that may not repeat inside one file.
     *
     * Two rows carrying the same email is not something the database would
     * catch until the second one was already being written, and by then the
     * message is about a constraint rather than about the spreadsheet.
     *
     * @return array<int, string>
     */
    public function uniqueColumns(): array
    {
        return [];
    }

    /** Forget what this pass has seen, so the file can be read again. */
    public function startPass(): void
    {
        $this->seen = [];
    }

    protected function clashWithinFile(array $row, int $line): ?string
    {
        foreach ($this->uniqueColumns() as $column) {
            $value = $row[$column] ?? null;

            if ($value === null) {
                continue;
            }

            $key = strtolower((string) $value);

            if (isset($this->seen[$column][$key])) {
                return sprintf(
                    'The %s "%s" is also on row %d of this file. Each one may only appear once.',
                    str_replace('_', ' ', $column),
                    $value,
                    $this->seen[$column][$key],
                );
            }

            $this->seen[$column][$key] = $line;
        }

        return null;
    }

    /**
     * Anything that cannot be expressed as a validation rule — mostly "does
     * this code name something that exists".
     *
     * @throws ValidationException
     */
    protected function checkReferences(array $row): void {}

    /** Normalise a raw row before it is validated or applied. */
    public function prepare(array $row): array
    {
        $prepared = collect($this->columns())
            ->mapWithKeys(fn (string $column) => [$column => $this->value($row, $column)])
            ->all();

        foreach ($this->numericColumns() as $column) {
            $prepared[$column] = $this->plainNumber($prepared[$column] ?? null);
        }

        return $prepared;
    }

    /**
     * Columns holding a number, so the separators a spreadsheet puts in are
     * taken out before the value is validated rather than after.
     *
     * "9,90,000" and "1,200.50" are how people write money; the validator only
     * knows what a number looks like once the commas are gone.
     *
     * @return array<int, string>
     */
    protected function numericColumns(): array
    {
        return [];
    }

    /** A number with its separators and currency symbols removed. */
    protected function plainNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[,\s\x{20B9}\x{00A0}]/u', '', $value) ?? $value;

        return is_numeric($clean) ? $clean : $value;
    }

    /** Work left until every row has been read, e.g. reporting lines. */
    public function finish(): void {}

    /** @return array<int, string> */
    public function columns(): array
    {
        return ImportTypes::columns($this->type());
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function attributes(): array
    {
        return collect($this->columns())
            ->mapWithKeys(fn (string $column) => [$column => str_replace('_', ' ', $column)])
            ->all();
    }

    /**
     * Other names a column is commonly exported under.
     *
     * An old system calls the employee code "Emp ID" and the joining date
     * "DOJ". Accepting those saves the person migrating from renaming forty
     * headers by hand before they can even try.
     *
     * @return array<string, array<int, string>> column => other names for it
     */
    public function aliases(): array
    {
        return [];
    }

    protected function value(array $row, string $column): ?string
    {
        $value = trim((string) ($row[$column] ?? ''));

        if ($value === '') {
            foreach ($this->aliases()[$column] ?? [] as $alias) {
                $value = trim((string) ($row[$alias] ?? ''));

                if ($value !== '') {
                    break;
                }
            }
        }

        return $value === '' ? null : $value;
    }

    /** A yes/no column as people write it: yes, y, true, 1, on. */
    protected function boolean(?string $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'yes', 'y', 'true', 't', 'on'], true);
    }

    /**
     * A date as people write it.
     *
     * Spreadsheets hand out whatever the machine's locale produced, so
     * 01/04/2026 has to be read the way an Indian payroll team means it —
     * the first of April — rather than the American way round.
     */
    protected function date(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        // Carbon throws rather than returning false on a format that does not
        // fit, so each attempt is its own try: the point of the loop is to
        // keep going until one of them works.
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd M Y', 'd-M-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $value) {
                return $date->startOfDay();
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** A time of day as people write it: 09:30, 9:30 AM, 0930. */
    protected function time(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d{4}$/', $value)) {
            $value = substr($value, 0, 2).':'.substr($value, 2);
        }

        foreach (['H:i', 'H:i:s', 'h:i A', 'h:iA', 'g:i A', 'g:iA'] as $format) {
            try {
                $time = Carbon::createFromFormat($format, strtoupper($value));
            } catch (\Throwable) {
                continue;
            }

            if ($time) {
                return $time->format('H:i');
            }
        }

        return null;
    }

    /** Money and day counts, with the thousands separators people paste in. */
    protected function number(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** Look something up by code once, then remember it. */
    protected function remember(string $bucket, string $key, callable $resolve)
    {
        $key = strtolower($key);

        return $this->lookups[$bucket][$key] ??= $resolve();
    }

    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
