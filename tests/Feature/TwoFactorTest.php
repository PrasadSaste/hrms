<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Roles;
use App\Support\TwoFactorPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = app(TwoFactorService::class);
    }

    /**
     * RFC 6238's own test vectors, appendix B.
     *
     * The published table is for an ASCII secret of "12345678901234567890";
     * base32 is only how a provisioning URI carries it, so the secret is
     * encoded here and the expected codes are the RFC's, untouched. If this
     * ever fails the arithmetic is wrong, not the expectation.
     */
    public static function rfcVectors(): array
    {
        return [
            'the first step after the epoch' => [59, '287082'],
            '2005-03-18' => [1111111109, '081804'],
            'one second later' => [1111111111, '050471'],
            '2009-02-13' => [1234567890, '005924'],
            '2033-05-18' => [2000000000, '279037'],
            'a long way out' => [20000000000, '353130'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function test_it_agrees_with_rfc_6238(int $timestamp, string $expected): void
    {
        $secret = $this->totp->base32Encode('12345678901234567890');

        $this->assertSame($expected, $this->totp->codeAt($secret, $timestamp));
    }

    public function test_base32_round_trips(): void
    {
        foreach (['12345678901234567890', random_bytes(20), 'a'] as $raw) {
            $this->assertSame($raw, $this->totp->base32Decode($this->totp->base32Encode($raw)));
        }
    }

    public function test_it_accepts_a_step_either_side_and_no_further(): void
    {
        $secret = $this->totp->generateSecret();
        $now = 1_700_000_000;
        $step = TwoFactorService::PERIOD;

        // A phone half a minute out, in either direction, still gets in.
        $this->assertNotNull($this->totp->verifyToStep($secret, $this->totp->codeAt($secret, $now - $step), $now));
        $this->assertNotNull($this->totp->verifyToStep($secret, $this->totp->codeAt($secret, $now), $now));
        $this->assertNotNull($this->totp->verifyToStep($secret, $this->totp->codeAt($secret, $now + $step), $now));

        // A minute out does not.
        $this->assertNull($this->totp->verifyToStep($secret, $this->totp->codeAt($secret, $now - 2 * $step), $now));
        $this->assertNull($this->totp->verifyToStep($secret, $this->totp->codeAt($secret, $now + 2 * $step), $now));
    }

    public function test_something_that_is_not_a_code_is_refused(): void
    {
        $secret = $this->totp->generateSecret();

        foreach (['', '12345', '1234567', 'abcdef', '   '] as $rubbish) {
            $this->assertNull($this->totp->verifyToStep($secret, $rubbish));
        }
    }

    // --------------------------------------------------------- against a user

    protected function userWithTwoFactor(string $role = Roles::EMPLOYEE): array
    {
        $employee = $this->makeEmployee($role);
        $secret = $this->totp->generateSecret();
        $codes = $this->totp->enable($employee->user, $secret);

        return [$employee->user->fresh(), $secret, $codes];
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $now = 1_700_000_000;
        $code = $this->totp->codeAt($secret, $now);

        $this->assertTrue($this->totp->verifyFor($user, $code, $now));

        // Read over a shoulder, it would otherwise stay good for the rest of
        // its half-minute.
        $this->assertFalse($this->totp->verifyFor($user->fresh(), $code, $now));
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$user, , $codes] = $this->userWithTwoFactor();

        $this->assertTrue($this->totp->consumeRecoveryCode($user, $codes[0]));
        $this->assertFalse($this->totp->consumeRecoveryCode($user->fresh(), $codes[0]));
        $this->assertCount(count($codes) - 1, $user->fresh()->recoveryCodes());
    }

    public function test_recovery_codes_are_stored_hashed_and_the_secret_encrypted(): void
    {
        [$user, $secret, $codes] = $this->userWithTwoFactor();

        $row = \DB::table('users')->where('id', $user->id)->first();

        $this->assertStringNotContainsString($secret, $row->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $row->two_factor_recovery_codes);
        $this->assertSame($secret, $user->twoFactorSecret(), 'It still reads back.');
    }

    public function test_the_secret_is_never_serialised(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }
}
