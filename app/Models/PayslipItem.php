<?php

namespace App\Models;

use App\Enums\ComponentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_id', 'salary_component_id', 'name', 'code',
        'type', 'amount', 'is_statutory', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComponentType::class,
            'amount' => 'float',
            'is_statutory' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
