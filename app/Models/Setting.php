<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    /**
     * Rows of this type hold something that must not be read back in the
     * clear — an SMTP password. They are encrypted in the column and kept out
     * of the shared cache entirely, because the cache store is the database
     * and putting the plaintext there would undo the encryption.
     *
     * Read them with secret(), never with get().
     */
    public const SECRET = 'encrypted';

    protected $fillable = ['key', 'value', 'group', 'type', 'label'];

    protected const CACHE_KEY = 'hrms.settings';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()
                ->where('type', '!=', self::SECRET)
                ->get()
                ->mapWithKeys(fn (self $s) => [$s->key => $s->castValue()])
                ->all();
        });
    }

    /**
     * A stored secret, in the clear.
     *
     * Read straight from the table rather than from allValues(), so the
     * plaintext never reaches the cache. Returns null when nothing is stored,
     * or when the value cannot be decrypted — a changed APP_KEY leaves rows
     * that are no longer readable, and a mailer without a password is a better
     * outcome than an exception on every request.
     */
    public static function secret(string $key): ?string
    {
        $value = static::query()
            ->where('key', $key)
            ->where('type', self::SECRET)
            ->value('value');

        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Store a secret encrypted, or remove it when nothing is given. */
    public static function putSecret(string $key, ?string $value, string $group = 'general'): void
    {
        if (blank($value)) {
            static::query()->where('key', $key)->delete();
            Cache::forget(self::CACHE_KEY);

            return;
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString($value), 'group' => $group, 'type' => self::SECRET],
        );
    }

    /** Whether a secret is stored, without reading it. */
    public static function hasSecret(string $key): bool
    {
        return static::query()->where('key', $key)->where('type', self::SECRET)->exists();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', string $type = 'string'): self
    {
        if (is_array($value)) {
            $value = json_encode($value);
            $type = 'json';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
            $type = 'boolean';
        }

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type],
        );
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
