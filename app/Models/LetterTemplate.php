<?php

namespace App\Models;

use App\Support\LetterTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * An administrator's wording for one kind of letter.
 *
 * Rows are overrides, not the whole story: a blank field falls back to the
 * default in `LetterTypes`, so a letter still reads properly on an
 * installation where nobody has opened this screen.
 */
class LetterTemplate extends Model
{
    protected $fillable = ['type', 'subject', 'body', 'updated_by'];

    protected const CACHE_KEY = 'hrms.letter-templates';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array<string, array<string, string|null>> */
    public static function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get()
            ->mapWithKeys(fn (self $t) => [$t->type => [
                'subject' => $t->subject,
                'body' => $t->body,
            ]])
            ->all());
    }

    /**
     * The wording in force for a type: the catalogue's, with anything an
     * administrator has saved laid over the top.
     *
     * @return array{subject: string, body: string}|null
     */
    public static function resolve(string $type): ?array
    {
        $defaults = LetterTypes::find($type);

        if (! $defaults) {
            return null;
        }

        $override = static::overrides()[$type] ?? [];

        return [
            'subject' => filled($override['subject'] ?? null) ? $override['subject'] : $defaults['subject'],
            'body' => filled($override['body'] ?? null) ? $override['body'] : $defaults['body'],
        ];
    }

    /** Whether this type has been reworded at all. */
    public static function isCustomised(string $type): bool
    {
        $override = static::overrides()[$type] ?? [];

        return filled($override['subject'] ?? null) || filled($override['body'] ?? null);
    }
}
