<?php

namespace App\Models;

use App\Support\AssetTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing the company owns.
 *
 * Its status is derived from its assignments rather than typed in: an asset
 * with an open assignment is issued, whatever anybody set. Only the states
 * that are nobody's assignment — in repair, lost, retired — are set by hand,
 * and an asset in one of those cannot be issued at all.
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes;

    public const IN_STOCK = 'in_stock';

    public const ISSUED = 'issued';

    public const IN_REPAIR = 'in_repair';

    public const LOST = 'lost';

    public const RETIRED = 'retired';

    /** @var array<string, string> */
    public const STATUSES = [
        self::IN_STOCK => 'In stock',
        self::ISSUED => 'Issued',
        self::IN_REPAIR => 'In repair',
        self::LOST => 'Lost',
        self::RETIRED => 'Retired',
    ];

    /** @var array<string, string> */
    public const CONDITIONS = [
        'new' => 'New',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
        'damaged' => 'Damaged',
    ];

    protected $fillable = [
        'asset_tag', 'type', 'name', 'description',
        'make', 'model', 'serial_number', 'details',
        'company_id', 'branch_id',
        'purchased_on', 'purchase_cost', 'warranty_expires_on',
        'condition', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'purchased_on' => 'date:Y-m-d',
            'warranty_expires_on' => 'date:Y-m-d',
            'purchase_cost' => 'float',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('issued_on')->latest('id');
    }

    /** The assignment that has not been returned, if there is one. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_on')->latestOfMany();
    }

    public function holder(): ?Employee
    {
        return $this->currentAssignment?->employee;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::IN_STOCK);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::ISSUED);
    }

    /** Assets a branch manager may see: their own branch, or unplaced ones. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->can('employees.view-any-branch') || $user->can('assets.manage')) {
            return $query;
        }

        $branchId = $user->employee?->branch_id;

        return $branchId
            ? $query->where(fn (Builder $q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            : $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Reading it
    |--------------------------------------------------------------------------
    */

    public function typeLabel(): string
    {
        return AssetTypes::label($this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? ucfirst((string) $this->condition);
    }

    public function statusColour(): string
    {
        return match ($this->status) {
            self::IN_STOCK => 'emerald',
            self::ISSUED => 'blue',
            self::IN_REPAIR => 'amber',
            self::LOST => 'rose',
            self::RETIRED => 'slate',
            default => 'slate',
        };
    }

    /** Whether this could be handed to somebody right now. */
    public function isAvailable(): bool
    {
        return $this->status === self::IN_STOCK;
    }

    public function isIssued(): bool
    {
        return $this->status === self::ISSUED;
    }

    /** The company expects this kind back on somebody's last day. */
    public function isReturnable(): bool
    {
        return AssetTypes::returnable($this->type);
    }

    /** One of the type's own fields, typed in when the asset was recorded. */
    public function detail(string $field): ?string
    {
        return $this->details[$field] ?? null;
    }

    /** @return array<string, array{label: string, type: string, required: bool}> */
    public function fields(): array
    {
        return AssetTypes::fieldsFor($this->type);
    }

    /** What to show beside the name: the serial, or whatever stands for one. */
    public function identifier(): ?string
    {
        if (filled($this->serial_number)) {
            return $this->serial_number;
        }

        foreach (['imei', 'mobile_number', 'registration_number', 'card_number', 'licence_key', 'asset_code'] as $field) {
            if (filled($value = $this->detail($field))) {
                return $value;
            }
        }

        return null;
    }
}
