<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'effective_from', 'effective_to', 'ctc_annual', 'basic_salary',
        'gross_monthly', 'currency', 'payment_mode', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'ctc_annual' => 'float',
            'basic_salary' => 'float',
            'gross_monthly' => 'float',
        ];
    }

    public const PAYMENT_MODES = [
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'upi' => 'UPI',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function monthlyCtc(): float
    {
        return round($this->ctc_annual / 12, 2);
    }

    public function totalEarnings(): float
    {
        return round($this->components
            ->filter(fn (SalaryStructureComponent $c) => $c->salaryComponent?->isEarning())
            ->sum('computed_amount'), 2);
    }

    public function totalDeductions(): float
    {
        return round($this->components
            ->filter(fn (SalaryStructureComponent $c) => $c->salaryComponent && ! $c->salaryComponent->isEarning())
            ->sum('computed_amount'), 2);
    }

    public function netMonthly(): float
    {
        return round($this->totalEarnings() - $this->totalDeductions(), 2);
    }
}
