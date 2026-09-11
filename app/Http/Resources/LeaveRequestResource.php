<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'is_paid' => $this->leaveType->is_paid,
                'color' => $this->leaveType->color,
            ]),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'day_type' => $this->day_type->value,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'contact_during_leave' => $this->contact_during_leave,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'applied_on' => $this->applied_on?->toIso8601String(),
            'actioned_at' => $this->actioned_at?->toIso8601String(),
            'approver_remarks' => $this->approver_remarks,
            'can_cancel' => $this->canBeCancelled(),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}
