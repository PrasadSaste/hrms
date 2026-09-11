<?php

namespace App\Models;

use App\Support\ImportTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spreadsheet, checked and then applied.
 */
class DataImport extends Model
{
    public const STATUS_CHECKED = 'checked';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type', 'original_filename', 'stored_path', 'status',
        'rows_total', 'rows_valid', 'rows_invalid',
        'rows_created', 'rows_updated', 'rows_skipped',
        'errors', 'options', 'failure_reason', 'user_id', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'options' => 'array',
            'imported_at' => 'datetime',
            'rows_total' => 'integer',
            'rows_valid' => 'integer',
            'rows_invalid' => 'integer',
            'rows_created' => 'integer',
            'rows_updated' => 'integer',
            'rows_skipped' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return ImportTypes::label($this->type);
    }

    public function isImported(): bool
    {
        return $this->status === self::STATUS_IMPORTED;
    }

    public function hasErrors(): bool
    {
        return $this->rows_invalid > 0;
    }

    /** Whether applying this file would write anything at all. */
    public function hasSomethingToImport(): bool
    {
        return ! $this->isImported() && $this->rows_valid > 0;
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->status === self::STATUS_FAILED => 'rose',
            $this->status === self::STATUS_IMPORTED && $this->rows_invalid > 0 => 'amber',
            $this->status === self::STATUS_IMPORTED => 'emerald',
            $this->rows_invalid > 0 => 'amber',
            default => 'sky',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IMPORTED => 'Imported',
            self::STATUS_FAILED => 'Failed',
            default => $this->rows_invalid > 0 ? 'Checked, with problems' : 'Checked',
        };
    }
}
