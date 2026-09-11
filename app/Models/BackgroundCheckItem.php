<?php

namespace App\Models;

use App\Support\BackgroundCheckRequirements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a new joiner was asked to produce, and what happened to it.
 */
class BackgroundCheckItem extends Model
{
    public const PENDING = 'pending';

    public const UPLOADED = 'uploaded';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'Not provided',
        self::UPLOADED => 'Awaiting review',
        self::VERIFIED => 'Verified',
        self::REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'background_check_id', 'requirement', 'status', 'is_required',
        'file_path', 'file_name', 'mime_type', 'size', 'uploaded_at',
        'details', 'remarks', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'details' => 'array',
            'size' => 'integer',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(BackgroundCheck::class, 'background_check_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function label(): string
    {
        return BackgroundCheckRequirements::label($this->requirement);
    }

    public function description(): string
    {
        return BackgroundCheckRequirements::find($this->requirement)['description'] ?? '';
    }

    /** @return array<string, array{label: string, type: string, required: bool}> */
    public function fields(): array
    {
        return BackgroundCheckRequirements::fieldsFor($this->requirement);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColour(): string
    {
        return match ($this->status) {
            self::VERIFIED => 'emerald',
            self::REJECTED => 'rose',
            self::UPLOADED => 'sky',
            default => 'slate',
        };
    }

    public function hasUpload(): bool
    {
        return $this->file_path !== null;
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    /**
     * Whether the employee still has something to do here: nothing uploaded, or
     * uploaded and sent back.
     */
    public function needsWork(): bool
    {
        return $this->is_required
            && ($this->status === self::REJECTED || ! $this->hasUpload());
    }

    /** The value typed against one of the requirement's fields. */
    public function detail(string $field): ?string
    {
        return $this->details[$field] ?? null;
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
