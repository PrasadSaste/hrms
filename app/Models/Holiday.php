<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'date', 'type', 'description', 'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_recurring' => 'boolean',
        ];
    }

    public const TYPES = [
        'public' => 'Public Holiday',
        'optional' => 'Optional Holiday',
        'restricted' => 'Restricted Holiday',
        'company' => 'Company Holiday',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Holidays that apply to a branch: branch-specific plus organisation-wide. */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id');
            if ($branchId) {
                $q->orWhere('branch_id', $branchId);
            }
        });
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
