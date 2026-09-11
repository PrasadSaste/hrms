<?php

namespace App\Models;

use App\Enums\ComponentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id', 'employee_id', 'company_id', 'slip_number', 'period_start', 'period_end',
        'working_days', 'present_days', 'paid_leave_days', 'unpaid_leave_days',
        'holiday_days', 'weekend_days', 'absent_days', 'lop_days', 'paid_days',
        'overtime_hours', 'overtime_rate', 'overtime_amount',
        'tax_regime', 'tax_deducted',
        'basic_salary', 'gross_earnings', 'total_deductions',
        'net_pay', 'net_pay_words', 'currency', 'payment_mode', 'payment_status',
        'payment_date', 'payment_reference', 'status', 'pdf_path', 'emailed_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'emailed_at' => 'datetime',
            'working_days' => 'float',
            'present_days' => 'float',
            'paid_leave_days' => 'float',
            'unpaid_leave_days' => 'float',
            'holiday_days' => 'float',
            'weekend_days' => 'float',
            'absent_days' => 'float',
            'lop_days' => 'float',
            'paid_days' => 'float',
            'overtime_hours' => 'float',
            'overtime_rate' => 'float',
            'overtime_amount' => 'float',
            'tax_deducted' => 'float',
            'basic_salary' => 'float',
            'gross_earnings' => 'float',
            'total_deductions' => 'float',
            'net_pay' => 'float',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sequence');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayslipItem::class)
            ->where('type', ComponentType::Earning->value)
            ->orderBy('sequence');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayslipItem::class)
            ->where('type', ComponentType::Deduction->value)
            ->orderBy('sequence');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function periodLabel(): string
    {
        return $this->period_start->format('F Y');
    }

    public function attendancePercentage(): float
    {
        return $this->working_days > 0
            ? round(($this->paid_days / $this->working_days) * 100, 1)
            : 0.0;
    }

    public function recalculateTotals(): void
    {
        $earnings = (float) $this->earnings()->sum('amount');
        $deductions = (float) $this->deductions()->sum('amount');

        $this->forceFill([
            'gross_earnings' => round($earnings, 2),
            'total_deductions' => round($deductions, 2),
            'net_pay' => round($earnings - $deductions, 2),
        ])->save();
    }
}
