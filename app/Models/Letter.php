<?php

namespace App\Models;

use App\Support\LetterTypes;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A letter that was actually issued to somebody.
 *
 * The words are stored, not the recipe for them. Re-rendering an old letter
 * from today's template would quietly rewrite what a former employee is
 * holding a signed copy of.
 */
class Letter extends Model
{
    protected $fillable = [
        'employee_id', 'company_id', 'signatory_id', 'type', 'reference', 'subject', 'body',
        'data', 'fields', 'signatory_name', 'signatory_designation', 'signature_path',
        'issued_on', 'effective_from', 'issued_by', 'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'fields' => 'array',
            'issued_on' => 'date:Y-m-d',
            'effective_from' => 'date:Y-m-d',
            'emailed_at' => 'datetime',
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

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function typeLabel(): string
    {
        return LetterTypes::label($this->type);
    }

    /** The letter's body as HTML, for the screen and the PDF. */
    public function html(): string
    {
        return Markdown::html($this->body, hardBreaks: true);
    }

    /**
     * The signature that was frozen onto this letter, as a data URI for the PDF.
     * Deliberately not the signatory's current one: this letter went out over
     * that image, and a replacement upload must not rewrite it.
     */
    public function signatureDataUri(): ?string
    {
        return Signatory::signatureDataUri($this->signature_path);
    }

    public function wasEmailed(): bool
    {
        return $this->emailed_at !== null;
    }

    public function filename(): string
    {
        return sprintf(
            '%s-%s-%s.pdf',
            str_replace('/', '-', $this->reference),
            $this->type,
            $this->employee?->employee_code ?? $this->employee_id,
        );
    }
}
