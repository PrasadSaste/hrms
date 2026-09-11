<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Roles;
use App\Support\TwoFactorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Signing in with a second factor, and the ways round it that must not work.
 */
class TwoFactorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = app(TwoFactorService::class);
    }

    protected function enrolled(string $role = Roles::EMPLOYEE): array
    {
        $employee = $this->makeEmployee($role);
        $secret = $this->totp->generateSecret();
        $codes = $this->totp->enable($employee->user, $secret);

        return [$employee->user->fresh(), $secret, $codes];
    }

    // ------------------------------------------------------------- the guard

    public function test_somebody_without_it_is_not_challenged(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get(route('dashboard'))->assertOk();
    }

    public function test_an_unchallenged_session_is_sent_to_the_challenge(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($user)->get(route('employees.index'))->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($user)->get(route('payslips.index'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_right_code_lets_them_in(): void
    {
        [$user, $secret] = $this->enrolled();

        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['code' => $this->totp->codeAt($secret)])
            ->assertRedirect();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_wrong_code_does_not(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_recovery_code_lets_them_in_once(): void
    {
        [$user, , $codes] = $this->enrolled();

        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])
            ->assertRedirect();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // The same code again, in a fresh session, is spent.
        $this->app['session']->flush();
        $this->actingAs($user->fresh())
            ->post(route('two-factor.verify'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('code');
    }

    // --------------------------------------------------------- the bypasses

    /**
     * The one that matters.
     *
     * Somebody holding a stolen password gets as far as the challenge. If the
     * setup screen were reachable from there they could simply turn the second
     * factor off — it asks for the password, which is precisely what they have.
     */
    public function test_an_unchallenged_session_cannot_reach_the_setup_screen(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)->get(route('two-factor.setup'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_an_unchallenged_session_cannot_turn_it_off(): void
    {
        [$user] = $this->enrolled();

        $this->actingAs($user)
            ->delete(route('two-factor.disable'), ['password' => 'Password123!'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertTrue($user->fresh()->hasTwoFactor(), 'It is still on.');
    }

    public function test_an_unchallenged_session_cannot_take_new_recovery_codes(): void
    {
        [$user, , $codes] = $this->enrolled();

        $this->actingAs($user)
            ->post(route('two-factor.recovery'), ['password' => 'Password123!'])
            ->assertRedirect(route('two-factor.challenge'));

        // The originals still work, so nothing was replaced behind the guard.
        $this->assertTrue($this->totp->consumeRecoveryCode($user->fresh(), $codes[0]));
    }

    public function test_signing_out_is_always_possible(): void
    {
        [$user] = $this->enrolled();

        // Being unable to leave a screen you cannot pass is the worst version
        // of this feature.
        $this->actingAs($user)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    // ------------------------------------------------------------ enrolment

    public function test_enrolling_needs_a_code_that_proves_the_phone_has_the_secret(): void
    {
        Mail::fake();
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get(route('two-factor.setup'))->assertOk();
        $secret = session('two-factor.pending');
        $this->assertNotNull($secret);

        // A wrong code enrols nobody.
        $this->actingAs($employee->user)
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($employee->user->fresh()->hasTwoFactor());

        $this->actingAs($employee->user)
            ->post(route('two-factor.confirm'), ['code' => $this->totp->codeAt($secret)])
            ->assertRedirect(route('two-factor.setup'));

        $this->assertTrue($employee->user->fresh()->hasTwoFactor());
        $this->assertCount(TwoFactorService::RECOVERY_CODES, session('two-factor.codes'));
    }

    public function test_turning_it_off_needs_the_password(): void
    {
        [$user, $secret] = $this->enrolled();
        $this->actingAs($user)->post(route('two-factor.verify'), ['code' => $this->totp->codeAt($secret)]);

        $this->actingAs($user)
            ->delete(route('two-factor.disable'), ['password' => 'not-the-password'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactor());

        $this->actingAs($user)
            ->delete(route('two-factor.disable'), ['password' => 'Password123!'])
            ->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    // ---------------------------------------------------------- enforcement

    public function test_nobody_is_required_by_default(): void
    {
        $this->seedReferenceData();

        $this->assertSame([], TwoFactorPolicy::requiredRoles());
        $this->assertFalse(TwoFactorPolicy::enforced());
    }

    public function test_a_required_role_is_sent_to_enrol_rather_than_refused(): void
    {
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);
        Setting::put(TwoFactorPolicy::SETTING, Roles::ACCOUNTANT, 'security');

        // Sent to the door, not shown a wall.
        $this->actingAs($accountant->user)->get(route('payroll.index'))
            ->assertRedirect(route('two-factor.setup'));
        $this->actingAs($accountant->user)->get(route('two-factor.setup'))->assertOk();

        // And somebody the requirement does not name is untouched.
        $employee = $this->makeEmployee(Roles::EMPLOYEE);
        $this->actingAs($employee->user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_required_role_cannot_turn_it_off_again(): void
    {
        [$user, $secret] = $this->enrolled(Roles::ACCOUNTANT);
        Setting::put(TwoFactorPolicy::SETTING, Roles::ACCOUNTANT, 'security');

        $this->actingAs($user)->post(route('two-factor.verify'), ['code' => $this->totp->codeAt($secret)]);

        $this->actingAs($user)->delete(route('two-factor.disable'), ['password' => 'Password123!'])
            ->assertSessionHas('error');
        $this->assertTrue($user->fresh()->hasTwoFactor());
    }

    public function test_an_unknown_role_in_the_setting_is_ignored(): void
    {
        $this->seedReferenceData();
        Setting::put(TwoFactorPolicy::SETTING, 'wizard,accountant', 'security');

        $this->assertSame([Roles::ACCOUNTANT], TwoFactorPolicy::requiredRoles());
    }

    // ------------------------------------------------- the administrator's out

    public function test_an_administrator_can_clear_a_lost_second_factor(): void
    {
        Mail::fake();
        [$user] = $this->enrolled();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('users.reset-two-factor', $user))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->hasTwoFactor());

        // And the person is told, because a quiet removal is what an intruder
        // would want.
        Mail::assertQueued(
            \App\Mail\TemplatedMail::class,
            fn ($mail) => $mail->hasTo($user->email),
        );
    }

    public function test_an_ordinary_employee_cannot_clear_somebody_elses(): void
    {
        [$user] = $this->enrolled();
        $other = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($other->user)
            ->post(route('users.reset-two-factor', $user))
            ->assertForbidden();

        $this->assertTrue($user->fresh()->hasTwoFactor());
    }
}
