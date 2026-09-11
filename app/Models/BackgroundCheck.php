<?php

namespace App\Models;

use App\Support\BackgroundCheckRequirements;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One employee's background verification.
 *
 * It moves forwards through invited → in progress → submitted → verified, and
 * can be sent back to the employee as many times as it takes. Nothing is
 * deleted on the way: a rejected item keeps its remarks so the reason survives
 * the next upload.
 */
class BackgroundCheck extends Model
{
    public const INVITED = 'invited';

    public const IN_PROGRESS = 'in_progress';

    public const SUBMITTED = 'submitted';

    public const CHANGES_REQUESTED = 'changes_requested';

    public const VERIFIED = 'verified';

    public const STATUSES = [
        self::INVITED => 'Invited',
        self::IN_PROGRESS => 'In progress',
        self::SUBMITTED => 'Awaiting review',
        self::CHANGES_REQUESTED => 'Changes requested',
        self::VERIFIED => 'Verified',
    ];

    protected $fillable = [
        'employee_id', 'status', 'due_on', 'invited_at', 'submitted_at',
        'reviewed_at', 'reviewed_by', 'review_remarks', 'onboarded_at', 'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date:Y-m-d',
            'invited_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'onboarded_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BackgroundCheckItem::class)->orderBy('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', self::SUBMITTED);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::VERIFIED);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::SUBMITTED;
    }

    /** Whether the employee may still change their answers. */
    public function isOpenToEmployee(): bool
    {
        return in_array($this->status, [self::INVITED, self::IN_PROGRESS, self::CHANGES_REQUESTED], true);
    }

    public function requiredItems(): Collection
    {
        return $this->items->where('is_required', true);
    }

    /** Everything required has a file against it, so it can go for review. */
    public function isReadyToSubmit(): bool
    {
        return $this->requiredItems()->isNotEmpty()
            && $this->requiredItems()->every(fn (BackgroundCheckItem $item) => $item->hasUpload());
    }

    /** Every required item has been looked at and accepted. */
    public function isFullyVerified(): bool
    {
        return $this->requiredItems()->isNotEmpty()
            && $this->requiredItems()->every(fn (BackgroundCheckItem $item) => $item->isVerified());
    }

    /** How far along the employee is, for a progress bar. */
    public function progress(): int
    {
        $required = $this->requiredItems();

        if ($required->isEmpty()) {
            return 0;
        }

        $done = $required->filter(fn (BackgroundCheckItem $item) => $item->hasUpload())->count();

        return (int) round($done / $required->count() * 100);
    }

    public function itemsNeedingWork(): Collection
    {
        return $this->items->filter(fn (BackgroundCheckItem $item) => $item->needsWork());
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null
            && ! $this->isVerified()
            && $this->due_on->isPast();
    }

    /** The requirements this check does not already cover. */
    public function missingRequirements(): array
    {
        return array_diff(BackgroundCheckRequirements::keys(), $this->items->pluck('requirement')->all());
    }
}
