<?php

namespace App\Models;

use App\Support\Money;
use App\Support\SettlementLines;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's full and final.
 *
 * Draft until somebody has checked it, approved once they have, paid when the
 * money has actually gone. Nothing is recomputed after approval: the figures
 * on the row are the figures on the statement in somebody's hands.
 */
class Settlement extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const PAID = 'paid';

    /** @var array<string, string> */
    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::APPROVED => 'Approved',
        self::PAID => 'Paid',
    ];

    protected $fillable = [
        'reference', 'employee_id', 'company_id',
        'date_of_joining', 'last_working_day', 'exit_reason',
        'last_drawn_basic', 'last_drawn_gross', 'service_years', 'per_day_divisor',
        'encashable_days', 'notice_required_days', 'notice_served_days',
        'notice_waived', 'gratuity_eligible',
        'total_earnings', 'total_deductions', 'net_payable', 'currency',
        'status', 'notes', 'settled_on',
        'prepared_by', 'approved_by', 'approved_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date:Y-m-d',
            'last_working_day' => 'date:Y-m-d',
            'settled_on' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_drawn_basic' => 'float',
            'last_drawn_gross' => 'float',
            'service_years' => 'float',
            'encashable_days' => 'float',
            'total_earnings' => 'float',
            'total_deductions' => 'float',
            'net_payable' => 'float',
            'notice_waived' => 'boolean',
            'gratuity_eligible' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class)->orderBy('sequence')->orderBy('id');
    }

    public function earnings(): HasMany
    {
        return $this->lines()->where('type', SettlementLines::EARNING);
    }

    public function deductions(): HasMany
    {
        return $this->lines()->where('type', SettlementLines::DEDUCTION);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::DRAFT);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereIn('status', [self::APPROVED, self::PAID]);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::APPROVED, self::PAID], true);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** A draft is the only thing anybody may still change. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusColour(): string
    {
        return match ($this->status) {
            self::DRAFT => 'amber',
            self::APPROVED => 'sky',
            self::PAID => 'emerald',
            default => 'slate',
        };
    }

    /** A settlement can come out owing the company money rather than the person. */
    public function isRecoverable(): bool
    {
        return $this->net_payable < 0;
    }

    public function netLabel(): string
    {
        return $this->isRecoverable() ? 'Recoverable from them' : 'Payable to them';
    }

    public function netInWords(): string
    {
        return Money::inWords(abs($this->net_payable), $this->currency);
    }

    public function filename(): string
    {
        return sprintf(
            'settlement-%s-%s.pdf',
            str($this->employee?->employee_code ?? 'employee')->slug(),
            str($this->reference)->slug(),
        );
    }
}
