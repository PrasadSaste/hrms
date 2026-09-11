<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Time-based one-time passwords, written out rather than pulled in.
 *
 * RFC 6238 is a page of arithmetic — HMAC-SHA1 over the number of thirty-second
 * steps since the epoch, truncated to six digits — and it publishes test
 * vectors. Writing it here rather than taking a package means the drift window
 * is a decision this codebase makes and documents, and that the suite can pin
 * the algorithm against the RFC's own numbers instead of against a library's
 * agreement with itself. The one thing not written here is the QR image:
 * Reed–Solomon error correction is not worth hand-rolling.
 *
 * Three decisions that matter more than the arithmetic:
 *
 * - **A window of one step either side.** Phones drift and people type slowly.
 *   Zero would reject honest codes near the boundary; three or four would widen
 *   the guessing surface for no real gain.
 * - **A code cannot be used twice.** Without that, a code shoulder-surfed or
 *   read off a screen stays good for the rest of its half-minute. The last
 *   accepted timestep is remembered against the user.
 * - **Recovery codes are hashed, single use, and shown exactly once.** They are
 *   passwords, not settings, so they are stored the way passwords are and the
 *   only chance to write them down is when they are made.
 */
class TwoFactorService
{
    /** The step, in seconds. Thirty is what every authenticator app assumes. */
    public const PERIOD = 30;

    public const DIGITS = 6;

    public const ALGORITHM = 'sha1';

    /**
     * How many steps either side of now are accepted.
     *
     * One, so a phone thirty seconds out and a person typing slowly both still
     * get in, and a guess is still one in a million across three windows.
     */
    public const WINDOW = 1;

    public const RECOVERY_CODES = 8;

    /** RFC 4648 base32, which is what a provisioning URI carries. */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh secret, 160 bits, which is what the RFC recommends for SHA-1. */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * The otpauth:// URI an authenticator app reads from the QR.
     *
     * The issuer appears twice on purpose — once in the label and once as a
     * parameter — because different apps read different ones, and an entry
     * that says only an email address is useless on a phone with several.
     */
    public function provisioningUri(User $user, string $secret, ?string $issuer = null): string
    {
        $issuer = $issuer ?: (string) config('app.name');
        $label = rawurlencode($issuer).':'.rawurlencode($user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /** The QR as an inline SVG data URI, so no image is ever written to disk. */
    public function qrCode(string $uri, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd,
        ));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($uri));
    }

    /** The code for one moment, which is what a test needs and a phone shows. */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        return $this->codeForStep($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    /**
     * Whether a typed code is right, and which step it belonged to.
     *
     * Returns the timestep rather than a boolean so the caller can refuse a
     * code that has already been used — the step is what makes a code unique.
     */
    public function verifyToStep(string $secret, string $code, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return null;
        }

        $now = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            // Constant time, so the comparison itself says nothing about how
            // nearly right a wrong guess was.
            if (hash_equals($this->codeForStep($secret, $now + $offset), $code)) {
                return $now + $offset;
            }
        }

        return null;
    }

    /** Whether a code is right *and* has not been used already. */
    public function verifyFor(User $user, string $code, ?int $timestamp = null): bool
    {
        $secret = $user->twoFactorSecret();

        if (! $secret) {
            return false;
        }

        $step = $this->verifyToStep($secret, $code, $timestamp);

        if ($step === null) {
            return false;
        }

        // A code already accepted is spent, however recently. Someone reading
        // it over a shoulder has half a minute otherwise.
        if ($user->two_factor_last_step !== null && $step <= $user->two_factor_last_step) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /**
     * A set of recovery codes, in the clear, for showing once.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = self::RECOVERY_CODES): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Spend a recovery code, if it is one of theirs.
     *
     * Hashed, so the stored set is useless to anybody who reads the table, and
     * removed on use, so a code written on a scrap of paper works once.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $stored = $user->recoveryCodes();

        foreach ($stored as $index => $hash) {
            if (password_verify($code, $hash)) {
                unset($stored[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(array_values($stored)),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /** Turn it on for somebody, returning the codes to show them once. */
    public function enable(User $user, string $secret): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt($this->hashAll($codes)),
            'two_factor_confirmed_at' => Carbon::now(),
            'two_factor_last_step' => null,
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /** A fresh set, replacing whatever is stored. */
    public function replaceRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt($this->hashAll($codes)),
        ])->save();

        return $codes;
    }

    /** @param  array<int, string>  $codes */
    protected function hashAll(array $codes): array
    {
        return array_map(fn (string $code) => password_hash($code, PASSWORD_DEFAULT), $codes);
    }

    /** The RFC's arithmetic: HMAC the step, then take four bytes from it. */
    protected function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);

        if ($key === '') {
            return str_repeat('x', self::DIGITS);   // never equal to a code
        }

        // The counter as eight bytes, big-endian.
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(self::ALGORITHM, $counter, $key, true);

        // Dynamic truncation: the low nibble of the last byte says where to
        // read from, and the top bit of that word is dropped so the result is
        // the same on a platform without unsigned integers.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret) ?? '');

        if ($secret === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
