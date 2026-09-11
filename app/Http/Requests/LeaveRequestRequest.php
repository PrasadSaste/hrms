<?php

namespace App\Http\Requests;

use App\Enums\DayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'day_type' => ['required', Rule::in(array_column(DayType::cases(), 'value'))],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'contact_during_leave' => ['nullable', 'string', 'max:64'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Please give a slightly longer reason so your approver has context.',
        ];
    }
}
