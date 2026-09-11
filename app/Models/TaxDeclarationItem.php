<?php

namespace App\Models;

use App\Support\TaxDeductionSections;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDeclarationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_declaration_id', 'section', 'declared_amount',
        'verified_amount', 'proof_path', 'note',
    ];

    protected function casts(): array
    {
        return [
            'declared_amount' => 'float',
            'verified_amount' => 'float',
        ];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(TaxDeclaration::class, 'tax_declaration_id');
    }

    public function definition(): ?array
    {
        return TaxDeductionSections::get($this->section);
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->section;
    }

    /**
     * The figure the arithmetic should use.
     *
     * Nobody having looked yet is not the same as having accepted nothing, so
     * an unverified item counts at what was declared. Once HR sets a figure —
     * including zero, for a proof that never arrived — that one governs.
     */
    public function effectiveAmount(): float
    {
        return round($this->verified_amount ?? $this->declared_amount, 2);
    }

    public function isVerified(): bool
    {
        return $this->verified_amount !== null;
    }
}
