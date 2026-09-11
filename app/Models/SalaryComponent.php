<?php

namespace App\Models;

use App\Enums\ComponentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'calculation_type', 'percentage_of', 'default_value',
        'wage_ceiling', 'eligibility_ceiling', 'slabs', 'statutory_note',
        'is_taxable', 'is_statutory', 'affects_gross', 'prorate_on_lop',
        'sequence', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComponentType::class,
            'default_value' => 'float',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
            'affects_gross' => 'boolean',
            'prorate_on_lop' => 'boolean',
            'sequence' => 'integer',
            'wage_ceiling' => 'float',
            'eligibility_ceiling' => 'float',
            'slabs' => 'array',
        ];
    }

    public const CALCULATION_TYPES = [
        'fixed' => 'Fixed amount',
        'percentage' => 'Percentage of base',
        'slab' => 'A slab table, by the base',
    ];

    public const PERCENTAGE_BASES = [
        'basic' => 'Basic salary',
        'gross' => 'Gross earnings',
        'ctc' => 'Monthly CTC',
    ];

    public function structureComponents(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', ComponentType::Earning->value);
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', ComponentType::Deduction->value);
    }

    public function isEarning(): bool
    {
        return $this->type === ComponentType::Earning;
    }

    /**
     * Resolve the monetary amount of this component for a given base set.
     *
     * @param  array{basic: float, gross: float, ctc: float}  $bases
     */
    /**
     * What this component comes to, for one person, this month.
     *
     * The statutory rules live here rather than in the payroll service,
     * because they are properties of the component itself: provident fund is
     * a percentage of a capped wage wherever it is used, and employee state
     * insurance stops applying above its ceiling for everybody.
     */
    public function resolveAmount(float $value, string $calculationType, array $bases): float
    {
        $base = (float) ($bases[$this->percentage_of ?? 'basic'] ?? 0.0);

        // Not charged at all above the ceiling. Employee state insurance is
        // this: somebody over the limit is outside the scheme, rather than
        // paying on a capped wage the way provident fund does.
        if ($this->eligibility_ceiling !== null && $base > $this->eligibility_ceiling) {
            return 0.0;
        }

        if ($calculationType === 'slab') {
            return $this->fromSlabs($base);
        }

        if ($calculationType !== 'percentage') {
            return round($value, 2);
        }

        // The wage the percentage is taken on, capped. Provident fund is 12%
        // of a basic capped at the statutory figure, so somebody on a large
        // basic contributes the same as somebody on the cap.
        if ($this->wage_ceiling !== null) {
            $base = min($base, (float) $this->wage_ceiling);
        }

        return round($base * $value / 100, 2);
    }

    /**
     * The amount for the slab this wage falls into.
     *
     * Professional tax is a table, set by each state. Slabs are read in the
     * order they are stored, and the first whose ceiling the wage does not
     * exceed wins; an entry with no ceiling is the top band.
     */
    public function fromSlabs(float $base): float
    {
        foreach ($this->slabs ?? [] as $slab) {
            $upTo = $slab['up_to'] ?? null;

            if ($upTo === null || $upTo === '' || $base <= (float) $upTo) {
                return round((float) ($slab['amount'] ?? 0), 2);
            }
        }

        return 0.0;
    }

    /** Whether any statutory rule is set on this component. */
    public function hasStatutoryRules(): bool
    {
        return $this->wage_ceiling !== null
            || $this->eligibility_ceiling !== null
            || filled($this->slabs);
    }
}
