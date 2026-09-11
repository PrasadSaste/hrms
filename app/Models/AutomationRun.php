<?php

namespace App\Models;

use App\Support\Automations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One run of one automation.
 *
 * Written when a job starts and closed when it ends, so a job that dies
 * halfway leaves a row still marked as running rather than no trace at all.
 */
class AutomationRun extends Model
{
    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $fillable = [
        'key', 'started_at', 'finished_at', 'status', 'summary', 'affected', 'error', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'affected' => 'integer',
        ];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function scopeFor(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /** Start recording a run. */
    public static function begin(string $key, ?int $userId = null): self
    {
        return static::create([
            'key' => $key,
            'started_at' => now(),
            'status' => self::RUNNING,
            'triggered_by' => $userId,
        ]);
    }

    public function succeed(string $summary, int $affected = 0): self
    {
        $this->update([
            'status' => self::SUCCEEDED,
            'finished_at' => now(),
            'summary' => Str::limit($summary, 250),
            'affected' => $affected,
        ]);

        return $this;
    }

    public function fail(\Throwable $e): self
    {
        $this->update([
            'status' => self::FAILED,
            'finished_at' => now(),
            'summary' => 'It did not finish.',
            'error' => Str::limit($e->getMessage(), 2000),
        ]);

        return $this;
    }

    public function label(): string
    {
        return Automations::find($this->key)['label'] ?? $this->key;
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }

    /** How long it took, for the screen. */
    public function duration(): ?string
    {
        if (! $this->finished_at) {
            return null;
        }

        $seconds = $this->started_at->diffInSeconds($this->finished_at);

        return $seconds < 60
            ? $seconds.'s'
            : Carbon::now()->subSeconds((int) $seconds)->diffForHumans(syntax: true);
    }
}
