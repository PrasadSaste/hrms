<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Somebody authorised to sign for a company.
 *
 * A company keeps a list of them with one marked as the default, so an offer
 * letter goes out over the right name without anybody retyping it, and a second
 * director can sign while the first is away. What a letter went out over is
 * frozen onto the letter itself — changing this record never rewrites history.
 */
class Signatory extends Model
{
    protected $table = 'signatories';

    protected $fillable = [
        'company_id', 'name', 'designation', 'email',
        'signature_path', 'is_default', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $signatory) {
            // One default per company, always.
            if ($signatory->is_default) {
                static::where('company_id', $signatory->company_id)
                    ->where('id', '!=', $signatory->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** "A Director, Director" — how the person reads in a list. */
    public function label(): string
    {
        return $this->designation ? $this->name.', '.$this->designation : $this->name;
    }

    public function hasSignature(): bool
    {
        return (bool) $this->signature_path && Storage::disk('local')->exists($this->signature_path);
    }

    /**
     * The specimen signature as a data URI.
     *
     * DomPDF renders offline and will not fetch over the network, so the bytes
     * have to travel with the document. SVG is skipped rather than printed as a
     * broken box, the same rule the logos follow.
     */
    public static function signatureDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        try {
            $bytes = Storage::disk('local')->get($path);
            $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
        } catch (\Throwable) {
            return null;
        }

        if (str_contains($mime, 'svg')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
