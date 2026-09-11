<?php

namespace App\Services\Import;

use App\Models\Branch;
use App\Models\Department;
use App\Support\ImportTypes;
use Illuminate\Validation\Rule;

class DepartmentImporter extends RowImporter
{
    public function type(): string
    {
        return ImportTypes::DEPARTMENTS;
    }

    public function aliases(): array
    {
        return [
            'code' => ['department_code', 'dept_code'],
            'name' => ['department_name', 'department', 'dept'],
            'branch_code' => ['branch'],
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function checkReferences(array $row): void
    {
        if (! $this->branch($row['branch_code'])) {
            $this->fail('branch_code', 'There is no branch with the code "'.$row['branch_code'].'". Load the branches first.');
        }
    }

    public function apply(array $row): string
    {
        $row = $this->prepare($row);
        $branch = $this->branch($row['branch_code']);

        $department = Department::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('code', $row['code'])
            ->first();

        $attributes = [
            'name' => $row['name'],
            'description' => $row['description'],
            'status' => $row['status'] ?? 'active',
        ];

        if ($department) {
            $department->restore();
            $department->update($attributes);

            return 'updated';
        }

        Department::create($attributes + [
            'code' => $row['code'],
            'branch_id' => $branch->id,
        ]);

        return 'created';
    }

    private function branch(?string $code): ?Branch
    {
        if ($code === null) {
            return null;
        }

        return $this->remember('branch', $code, fn () => Branch::where('code', $code)->first());
    }
}
