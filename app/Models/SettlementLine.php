<?php

namespace App\Models;

use App\Support\SettlementLines;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a settlement, and how it was arrived at.
 *
 * `basis` is the reason in words, kept beside the number. "Gratuity ₹1,73,076"
 * is not a defensible line on its own, and it is precisely the line somebody
 * queries a year later when the person who prepared it has left too.
 */
class SettlementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id', 'key', 'label', 'type', 'amount', 'basis', 'is_computed', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'is_computed' => 'boolean',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function isEarning(): bool
    {
        return $this->type === SettlementLines::EARNING;
    }
}
