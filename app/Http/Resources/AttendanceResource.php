<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'worked_minutes' => $this->worked_minutes,
            'worked_hours' => $this->workedHours(),
            'late_minutes' => $this->late_minutes,
            'early_leaving_minutes' => $this->early_leaving_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'check_in_location' => $this->when($this->hasCheckInCoordinates(), fn () => [
                'latitude' => $this->check_in_latitude,
                'longitude' => $this->check_in_longitude,
                'accuracy_metres' => $this->check_in_accuracy,
                'label' => $this->check_in_location,
            ]),
            'check_out_location' => $this->when($this->hasCheckOutCoordinates(), fn () => [
                'latitude' => $this->check_out_latitude,
                'longitude' => $this->check_out_longitude,
                'accuracy_metres' => $this->check_out_accuracy,
                'label' => $this->check_out_location,
            ]),
            'remarks' => $this->remarks,
            'is_open' => $this->isOpen(),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}
