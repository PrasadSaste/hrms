<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware + policies handle access
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('branches', 'code')->ignore($branchId)->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            // Both or neither: half a coordinate places nothing.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'geofence_radius_metres' => ['nullable', 'integer', 'between:20,50000'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'saturday_offs' => ['nullable', 'array'],
            'saturday_offs.*' => ['integer', 'between:1,5'],
            'is_head_office' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_with' => 'Give both the latitude and the longitude, or neither.',
            'longitude.required_with' => 'Give both the latitude and the longitude, or neither.',
            'geofence_radius_metres.between' => 'The radius must be between 20 metres and 50 km. '
                .'Leave it empty to use the organisation-wide default.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_head_office' => $this->boolean('is_head_office'),
            'working_days' => array_map('intval', (array) $this->input('working_days', [1, 2, 3, 4, 5])),
            'saturday_offs' => array_values(array_map('intval', (array) $this->input('saturday_offs', []))),
            // An empty box means "not set", not "zero".
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
            'geofence_radius_metres' => $this->filled('geofence_radius_metres')
                ? $this->input('geofence_radius_metres')
                : null,
        ]);
    }
}
