<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:25:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function tokenFor(Employee $employee): string
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $employee->email,
            'password' => 'Password123!',
            'device_name' => 'test-suite',
        ]);

        return $response->json('token');
    }

    public function test_login_returns_a_token_and_the_user_profile(): void
    {
        $employee = $this->makeEmployee();

        $this->postJson('/api/v1/login', [
            'email' => $employee->email,
            'password' => 'Password123!',
            'device_name' => 'test-suite',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'expires_at',
                'must_change_password',
                'user' => ['id', 'name', 'email', 'roles', 'permissions', 'employee'],
            ])
            ->assertJsonPath('user.email', $employee->email)
            ->assertJsonPath('user.employee.employee_code', $employee->employee_code);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $employee = $this->makeEmployee();

        $this->postJson('/api/v1/login', [
            'email' => $employee->email,
            'password' => 'not-the-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_a_deactivated_account(): void
    {
        $employee = $this->makeEmployee();
        $employee->user->update(['status' => 'inactive']);

        $this->postJson('/api/v1/login', [
            'email' => $employee->email,
            'password' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_protected_endpoints_reject_an_anonymous_caller(): void
    {
        foreach (['/api/v1/me', '/api/v1/payslips', '/api/v1/leave/balance', '/api/v1/attendance/today'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_the_me_endpoint_returns_roles_and_permissions(): void
    {
        $employee = $this->makeEmployee(Roles::HR_MANAGER);
        Sanctum::actingAs($employee->user);

        $response = $this->getJson('/api/v1/me')->assertOk();

        $this->assertContains(Roles::HR_MANAGER, $response->json('user.roles'));
        $this->assertContains('employees.view', $response->json('user.permissions'));
    }

    public function test_an_employee_can_punch_in_and_out_over_the_api(): void
    {
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('checked_in', false);

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => 12.9352, 'longitude' => 77.6245, 'location' => 'Office',
        ])
            ->assertCreated()
            ->assertJsonPath('attendance.status', 'present');

        Carbon::setTestNow(Carbon::parse('2026-06-10 18:30:00'));

        $this->postJson('/api/v1/attendance/check-out', [
            'latitude' => 12.9352, 'longitude' => 77.6245,
        ])
            ->assertOk()
            ->assertJsonPath('attendance.worked_minutes', 485);

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('checked_out', true);
    }

    public function test_punching_in_twice_returns_a_validation_error(): void
    {
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $position = ['latitude' => 12.9352, 'longitude' => 77.6245];

        $this->postJson('/api/v1/attendance/check-in', $position)->assertCreated();
        $this->postJson('/api/v1/attendance/check-in', $position)->assertStatus(422);
    }

    public function test_the_attendance_summary_returns_totals_and_days(): void
    {
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/attendance/summary?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('totals.working_days', 22)
            ->assertJsonCount(30, 'days');
    }

    public function test_leave_balance_lists_the_applicable_types(): void
    {
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $response = $this->getJson('/api/v1/leave/balance')->assertOk();

        $codes = collect($response->json('data'))->pluck('leave_type.code');

        $this->assertTrue($codes->contains('CL'));
        $this->assertTrue($codes->contains('SL'));
    }

    public function test_an_employee_can_apply_for_leave_over_the_api(): void
    {
        $employee = $this->makeEmployee();
        $type = LeaveType::where('code', 'CL')->firstOrFail();
        Sanctum::actingAs($employee->user);

        $this->postJson('/api/v1/leave', [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'day_type' => 'full_day',
            'reason' => 'Family commitment out of town.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_days', 2);
    }

    public function test_the_api_enforces_leave_policy(): void
    {
        $employee = $this->makeEmployee();
        $type = LeaveType::where('code', 'CL')->firstOrFail();
        Sanctum::actingAs($employee->user);

        // Casual leave caps at three consecutive days.
        $this->postJson('/api/v1/leave', [
            'leave_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-26',
            'day_type' => 'full_day',
            'reason' => 'Too long for this leave type.',
        ])->assertStatus(422);
    }

    public function test_an_employee_cannot_approve_leave_over_the_api(): void
    {
        $employee = $this->makeEmployee();
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $employee->branch]);

        $request = app(LeaveService::class)->apply($colleague, [
            'leave_type_id' => LeaveType::where('code', 'CL')->firstOrFail()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => 'full_day',
            'reason' => 'Personal matter.',
        ]);

        Sanctum::actingAs($employee->user);

        $this->postJson("/api/v1/leave/{$request->id}/approve")->assertForbidden();
    }

    public function test_a_manager_sees_pending_approvals(): void
    {
        $manager = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);

        app(LeaveService::class)->apply($employee, [
            'leave_type_id' => LeaveType::where('code', 'CL')->firstOrFail()->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'day_type' => 'full_day',
            'reason' => 'Personal matter.',
        ]);

        Sanctum::actingAs($manager->user);

        $this->getJson('/api/v1/leave/pending-approvals')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_holidays_and_announcements_are_readable(): void
    {
        $employee = $this->makeEmployee();

        Holiday::create(['name' => 'Founders Day', 'date' => '2026-06-17', 'type' => 'public']);

        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/holidays?year=2026')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Founders Day');

        $this->getJson('/api/v1/announcements')->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_the_dashboard_endpoint_returns_a_summary(): void
    {
        $employee = $this->makeEmployee();
        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['today', 'self' => ['checked_in', 'month_totals', 'leave_balance']]);
    }

    public function test_signing_out_revokes_the_token(): void
    {
        $employee = $this->makeEmployee();
        $token = $this->tokenFor($employee);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $employee->user->tokens()->count());

        // The guard caches the resolved user within a test, so clear it before
        // checking that the revoked token is genuinely refused.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_an_employee_only_sees_their_own_payslips(): void
    {
        $employee = $this->makeEmployee();
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $employee->branch]);

        Sanctum::actingAs($employee->user);

        $this->getJson('/api/v1/payslips')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_directory_respects_visibility_rules(): void
    {
        $branch = $this->makeBranch();
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branch]);
        $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $branch]);

        Sanctum::actingAs($employee->user);

        // A plain employee sees only their own record.
        $this->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_an_hr_manager_sees_the_whole_directory(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $this->makeEmployee(Roles::EMPLOYEE);
        $this->makeEmployee(Roles::EMPLOYEE);

        Sanctum::actingAs($hr->user);

        $this->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_a_deactivated_user_is_blocked_even_with_a_valid_token(): void
    {
        $employee = $this->makeEmployee();
        $token = $this->tokenFor($employee);

        $employee->user->update(['status' => 'inactive']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }
}
