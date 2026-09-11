<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'company_id', 'branch_id', 'title', 'month', 'year', 'period_start', 'period_end',
        'payment_date', 'status', 'total_employees', 'total_gross', 'total_deductions',
        'total_net', 'notes', 'generated_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'status' => PayrollStatus::class,
            'month' => 'integer',
            'year' => 'integer',
            'total_employees' => 'integer',
            'total_gross' => 'float',
            'total_deductions' => 'float',
            'total_net' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [PayrollStatus::Approved, PayrollStatus::Paid], true);
    }

    public function recalculateTotals(): void
    {
        $this->forceFill([
            'total_employees' => $this->payslips()->count(),
            'total_gross' => (float) $this->payslips()->sum('gross_earnings'),
            'total_deductions' => (float) $this->payslips()->sum('total_deductions'),
            'total_net' => (float) $this->payslips()->sum('net_pay'),
        ])->save();
    }
}
