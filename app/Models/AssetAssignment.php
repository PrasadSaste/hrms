<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One period during which one person held one asset.
 *
 * The row is never edited to say somebody else has it — a handover closes this
 * one and opens another. That is what makes the history worth having: three
 * years later it can still say who had the laptop when the screen was cracked.
 */
class AssetAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'employee_id',
        'issued_on', 'issued_by', 'condition_out', 'issue_remarks',
        'returned_on', 'received_by', 'condition_in', 'return_remarks',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
            'returned_on' => 'date:Y-m-d',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Still out: the one question this table is asked most. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('returned_on');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_on');
    }

    public function isOpen(): bool
    {
        return $this->returned_on === null;
    }

    /** How long they have had it, or had it for. */
    public function heldDays(): int
    {
        return (int) $this->issued_on->diffInDays($this->returned_on ?? Carbon::today());
    }

    /** Came back worse than it went out. */
    public function deteriorated(): bool
    {
        if ($this->condition_in === null) {
            return false;
        }

        $order = array_keys(Asset::CONDITIONS);

        return array_search($this->condition_in, $order, true) > array_search($this->condition_out, $order, true);
    }
}
