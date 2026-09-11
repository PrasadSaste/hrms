<?php

namespace App\Models;

use App\Support\FinancialYear;
use App\Support\TaxRegimes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxDeclaration extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const VERIFIED = 'verified';

    public const RETURNED = 'returned';

    protected $fillable = [
        'employee_id', 'financial_year', 'regime', 'metro',
        'status', 'submitted_at', 'verified_at', 'verified_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'financial_year' => 'integer',
            'metro' => 'boolean',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaxDeclarationItem::class);
    }

    public function yearLabel(): string
    {
        return FinancialYear::label($this->financial_year);
    }

    public function regimeLabel(): string
    {
        return TaxRegimes::label($this->regime);
    }

    /** Whether the employee can still change it. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::RETURNED], true);
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    public static function statuses(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Awaiting verification',
            self::VERIFIED => 'Verified',
            self::RETURNED => 'Sent back',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::VERIFIED => 'emerald',
            self::SUBMITTED => 'amber',
            self::RETURNED => 'rose',
            default => 'slate',
        };
    }
}
