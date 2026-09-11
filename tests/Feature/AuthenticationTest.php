<?php

namespace Tests\Feature;

use App\Notifications\ResetPasswordNotification;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_reachable_by_a_guest(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in to your account');
    }

    public function test_a_user_can_sign_in_with_correct_credentials(): void
    {
        $employee = $this->makeEmployee();

        $response = $this->post('/login', [
            'email' => $employee->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($employee->user);
    }

    public function test_signing_in_records_the_last_login_timestamp(): void
    {
        $employee = $this->makeEmployee();
        $this->assertNull($employee->user->last_login_at);

        $this->post('/login', ['email' => $employee->email, 'password' => 'Password123!']);

        $this->assertNotNull($employee->user->fresh()->last_login_at);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $employee = $this->makeEmployee();

        $this->post('/login', ['email' => $employee->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $employee = $this->makeEmployee();
        $employee->user->update(['status' => 'inactive']);

        $this->post('/login', ['email' => $employee->email, 'password' => 'Password123!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $employee = $this->makeEmployee();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $employee->email, 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => $employee->email, 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('email'),
        );
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_can_sign_out(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_temporary_password_forces_a_change_before_anything_else(): void
    {
        $employee = $this->makeEmployee();
        $employee->user->update(['must_change_password' => true]);

        $this->actingAs($employee->user)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    public function test_changing_the_temporary_password_clears_the_flag(): void
    {
        $employee = $this->makeEmployee();
        $employee->user->update(['must_change_password' => true]);

        $this->actingAs($employee->user)->post(route('password.change.update'), [
            'current_password' => 'Password123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($employee->user->fresh()->must_change_password);
    }

    public function test_a_password_reset_link_is_emailed(): void
    {
        Notification::fake();
        $employee = $this->makeEmployee();

        $this->post(route('password.email'), ['email' => $employee->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($employee->user, ResetPasswordNotification::class);
    }

    public function test_the_reset_form_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_super_admin_reaches_the_dashboard(): void
    {
        $employee = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($employee->user)
            ->get('/dashboard')
            ->assertOk();
    }
}
