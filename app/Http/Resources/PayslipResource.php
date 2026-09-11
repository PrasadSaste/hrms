<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slip_number' => $this->slip_number,
            'period' => $this->periodLabel(),
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'working_days' => $this->working_days,
            'paid_days' => $this->paid_days,
            'lop_days' => $this->lop_days,
            'basic_salary' => $this->basic_salary,
            'gross_earnings' => $this->gross_earnings,
            'total_deductions' => $this->total_deductions,
            'net_pay' => $this->net_pay,
            'net_pay_words' => $this->net_pay_words,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status,
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'download_url' => route('api.payslips.download', $this->id),
            'earnings' => $this->whenLoaded('earnings', fn () => $this->earnings->map(fn ($i) => [
                'name' => $i->name, 'code' => $i->code, 'amount' => $i->amount,
            ])),
            'deductions' => $this->whenLoaded('deductions', fn () => $this->deductions->map(fn ($i) => [
                'name' => $i->name, 'code' => $i->code, 'amount' => $i->amount,
            ])),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}
