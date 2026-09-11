<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * A legal entity that employs people and pays them.
 *
 * A group runs several: an employee belongs to exactly one, payroll is run for
 * one at a time, and the salary slip carries that company's name, registration
 * numbers and logo. Branches are separate — where someone sits has nothing to
 * do with who signs their payslip.
 */
class Company extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'code',
        'registration_number', 'tax_id', 'gst_number', 'pf_number', 'esi_number',
        'email', 'phone', 'website',
        'address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code',
        'logo_path', 'letterhead_logo_path', 'letterhead_footer',
        'watermark_enabled', 'watermark_text',
        'currency', 'payslip_prefix',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_file_format',
        'signatory_name', 'signatory_designation',
        'status', 'is_default',
    ];

    protected const CACHE_KEY = 'hrms.default-company';

    /**
     * Matches the column defaults, so a company that has been built but not yet
     * read back from the database answers the same as one that has.
     */
    protected $attributes = [
        'watermark_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'watermark_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $company) {
            Cache::forget(self::CACHE_KEY);

            // Exactly one default, always: marking one demotes the rest.
            if ($company->is_default) {
                static::where('id', '!=', $company->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(Signatory::class)->ordered();
    }

    /**
     * The code the multi-company migration gives its placeholder entity.
     *
     * That migration creates one company from the old single-company settings
     * so nothing is left without an employer. On a system that had records
     * already it is the real entity; on a fresh one it is an artefact of
     * having run the migration at all, and the first real company absorbs it.
     */
    public const PLACEHOLDER_CODE = 'CO1';

    /**
     * Take over the migration's placeholder entity, if it is still about.
     *
     * Everything it carries is moved across before it goes, so an installation
     * that put records against it during an upgrade loses nothing.
     */
    public function absorbPlaceholder(): void
    {
        static::query()
            ->where('code', self::PLACEHOLDER_CODE)
            ->whereKeyNot($this->getKey())
            ->get()
            ->each(function (self $placeholder): void {
                $placeholder->employees()->update(['company_id' => $this->getKey()]);
                $placeholder->payrolls()->update(['company_id' => $this->getKey()]);
                $placeholder->payslips()->update(['company_id' => $this->getKey()]);
                $placeholder->delete();
            });
    }

    /**
     * Whose name goes at the foot of this company's documents.
     *
     * The one marked default, else the first one on the list. A company that
     * predates the list falls back to the single name on its own record, so an
     * installation that never opened the screen still prints what it always did.
     */
    public function defaultSignatory(): ?Signatory
    {
        return $this->signatories()->active()->ordered()->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The company a new employee falls into when nobody chooses one. */
    public static function default(): ?self
    {
        $id = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->where('is_default', true)->value('id')
                ?? static::query()->active()->orderBy('id')->value('id'),
        );

        return $id ? static::find($id) : null;
    }

    /** For a select box: id => name. */
    public static function options(): array
    {
        return static::query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** The address as it prints on a salary slip. */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            implode(', ', array_filter([$this->city, $this->state, $this->postal_code])),
            $this->country,
        ]));
    }

    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    /**
     * The words printed faintly across its documents.
     *
     * The short name unless something else is set: a watermark that says who
     * issued the paper is the point of having one, so it defaults rather than
     * being left blank.
     */
    public function watermark(): ?string
    {
        if (! $this->watermark_enabled) {
            return null;
        }

        return trim((string) $this->watermark_text) ?: $this->name;
    }

    /** Whether removing this company would orphan people or pay records. */
    public function isInUse(): bool
    {
        return $this->employees()->exists() || $this->payrolls()->exists();
    }
}
