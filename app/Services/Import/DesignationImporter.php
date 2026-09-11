<?php

namespace App\Services\Import;

use App\Models\Designation;
use App\Support\ImportTypes;
use Illuminate\Validation\Rule;

class DesignationImporter extends RowImporter
{
    public function type(): string
    {
        return ImportTypes::DESIGNATIONS;
    }

    public function uniqueColumns(): array
    {
        return ['code'];
    }

    protected function numericColumns(): array
    {
        return ['level'];
    }

    public function aliases(): array
    {
        return [
            'code' => ['designation_code', 'title_code'],
            'name' => ['designation', 'job_title', 'title'],
            'level' => ['grade', 'seniority'],
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'between:1,20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);

        $designation = Designation::withTrashed()->where('code', $row['code'])->first();

        $attributes = [
            'name' => $row['name'],
            'level' => $row['level'] ? (int) $row['level'] : 1,
            'description' => $row['description'],
            'status' => $row['status'] ?? 'active',
        ];

        if ($designation) {
            $designation->restore();
            $designation->update($attributes);

            return 'updated';
        }

        Designation::create($attributes + ['code' => $row['code']]);

        return 'created';
    }
}
