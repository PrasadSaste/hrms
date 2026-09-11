<?php

namespace App\Http\Requests;

use App\Services\AttendanceService;
use App\Support\PunchLocation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the coordinates sent with a check in or check out.
 *
 * When the organisation requires a location, a punch without one is refused
 * here rather than silently recorded, so the rule holds for the web form, the
 * API and anything else that posts to these routes.
 */
class PunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = AttendanceService::locationRequired() ? 'required' : 'nullable';

        return [
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        $message = 'Your location is required to record attendance. '
            .'Allow location access in your browser and try again.';

        return [
            'latitude.required' => $message,
            'longitude.required' => $message,
            'latitude.numeric' => 'The location reported by your device was not readable.',
            'longitude.numeric' => 'The location reported by your device was not readable.',
        ];
    }

    public function location(): PunchLocation
    {
        return PunchLocation::fromRequest($this);
    }
}
