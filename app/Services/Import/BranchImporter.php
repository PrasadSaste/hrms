<?php

namespace App\Services\Import;

use App\Models\Branch;
use App\Support\ImportTypes;
use Illuminate\Validation\Rule;

class BranchImporter extends RowImporter
{
    /** Monday first, as the working-days column is written. */
    private const DAYS = [
        'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7,
    ];

    public function type(): string
    {
        return ImportTypes::BRANCHES;
    }

    public function uniqueColumns(): array
    {
        return ['code'];
    }

    protected function numericColumns(): array
    {
        return ['latitude', 'longitude', 'geofence_radius_metres'];
    }

    public function aliases(): array
    {
        return [
            'code' => ['branch_code'],
            'name' => ['branch_name', 'branch'],
            'geofence_radius_metres' => ['radius', 'geofence_radius'],
            'saturday_offs' => ['saturdays_off', 'alternate_saturdays', 'saturday_off_weeks'],
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_metres' => ['nullable', 'integer', 'between:20,50000'],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function checkReferences(array $row): void
    {
        foreach (['work_start_time', 'work_end_time'] as $column) {
            if ($row[$column] !== null && $this->time($row[$column]) === null) {
                $this->fail($column, 'The '.str_replace('_', ' ', $column).' "'.$row[$column].'" is not a time. Use 24-hour, e.g. 09:30.');
            }
        }

        if ($row['working_days'] !== null && $this->workingDays($row['working_days']) === []) {
            $this->fail('working_days', 'Working days "'.$row['working_days'].'" could not be read. Use "1,2,3,4,5" or "Mon-Fri".');
        }

        if ($row['saturday_offs'] !== null && $this->saturdayOffs($row['saturday_offs']) === []) {
            $this->fail('saturday_offs', 'Saturdays off "'.$row['saturday_offs'].'" could not be read. '
                .'Use the ordinals, e.g. "1,3" for the first and third Saturday.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $branch = Branch::withTrashed()->where('code', $row['code'])->first();
        $existed = $branch !== null;

        $attributes = [
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address_line1' => $row['address_line1'],
            'address_line2' => $row['address_line2'],
            'city' => $row['city'],
            'state' => $row['state'],
            'country' => $row['country'] ?? 'India',
            'postal_code' => $row['postal_code'],
            'latitude' => $this->number($row['latitude']),
            'longitude' => $this->number($row['longitude']),
            'geofence_radius_metres' => $row['geofence_radius_metres'] ? (int) $row['geofence_radius_metres'] : null,
            'timezone' => $row['timezone'] ?? config('app.timezone'),
            'work_start_time' => $this->time($row['work_start_time']) ?? '09:30',
            'work_end_time' => $this->time($row['work_end_time']) ?? '18:30',
            'working_days' => $this->workingDays($row['working_days']) ?: [1, 2, 3, 4, 5],
            'saturday_offs' => $this->saturdayOffs($row['saturday_offs']),
            'is_head_office' => $this->boolean($row['is_head_office']),
            'status' => $row['status'] ?? 'active',
        ];

        if ($existed) {
            $branch->restore();
            $branch->update($attributes);

            return 'updated';
        }

        Branch::create($attributes + ['code' => $row['code']]);

        return 'created';
    }

    /**
     * "Mon-Fri", "Mon,Tue,Wed", "1,2,3,4,5" and "Monday to Friday" all mean
     * the same thing to the person filling in the sheet.
     *
     * @return array<int, int>
     */
    private function workingDays(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $value = strtolower(str_replace([' to ', '–', '—'], ['-', '-', '-'], trim($value)));

        // A range: mon-fri, 1-5.
        if (preg_match('/^([a-z0-9]+)\s*-\s*([a-z0-9]+)$/', $value, $matches)) {
            $from = $this->dayNumber($matches[1]);
            $to = $this->dayNumber($matches[2]);

            if ($from && $to && $from <= $to) {
                return range($from, $to);
            }

            return [];
        }

        $days = collect(preg_split('/[\s,;]+/', $value))
            ->map(fn (string $day) => $this->dayNumber($day))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $days;
    }

    /**
     * "1,3", "1st and 3rd", "1st & 3rd" — all the first and third Saturday.
     *
     * @return array<int, int>
     */
    private function saturdayOffs(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return collect(preg_split('/[\s,;&]+/', strtolower($value)))
            ->map(fn (string $week) => (int) preg_replace('/[^0-9]/', '', $week))
            ->filter(fn (int $week) => $week >= 1 && $week <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function dayNumber(string $day): ?int
    {
        $day = substr(trim($day), 0, 3);

        if (is_numeric($day)) {
            $number = (int) $day;

            return $number >= 1 && $number <= 7 ? $number : null;
        }

        return self::DAYS[$day] ?? null;
    }
}
