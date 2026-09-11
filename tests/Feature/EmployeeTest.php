<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_an_employee_with_a_login(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Rahman',
            'email' => 'nadia.rahman@example.test',
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'department_id' => $hr->department_id,
            'designation_id' => $hr->designation_id,
            'employment_type' => 'full_time',
            'employment_status' => 'probation',
            'date_of_joining' => Carbon::today()->toDateString(),
            'notice_period_days' => 30,
            'status' => 'active',
            'create_account' => '1',
            'role' => Roles::EMPLOYEE,
            'send_welcome_email' => '1',
        ])->assertRedirect();

        $employee = Employee::where('email', 'nadia.rahman@example.test')->first();

        $this->assertNotNull($employee);
        $this->assertNotNull($employee->user);
        $this->assertTrue($employee->user->hasRole(Roles::EMPLOYEE));
        $this->assertTrue($employee->user->must_change_password);

        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::EMPLOYEE_WELCOME,
        );
    }

    public function test_employee_codes_are_generated_in_sequence(): void
    {
        $this->seedReferenceData();
        $service = app(EmployeeService::class);

        $first = $service->nextEmployeeCode();
        $this->assertMatchesRegularExpression('/^EMP\d{4}$/', $first);

        Employee::create([
            'employee_code' => $first,
            'first_name' => 'Seed',
            'email' => 'seed@example.test',
            'date_of_joining' => Carbon::today(),
            'branch_id' => $this->makeBranch()->id,
        ]);

        $second = $service->nextEmployeeCode();

        $this->assertNotSame($first, $second);
        $this->assertSame(
            (int) substr($first, 3) + 1,
            (int) substr($second, 3),
        );
    }

    public function test_creating_an_employee_seeds_their_leave_allocations(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        $branch = $this->makeBranch();

        $result = app(EmployeeService::class)->create([
            'first_name' => 'Nadia',
            'last_name' => 'Rahman',
            'email' => 'nadia.rahman@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'date_of_joining' => Carbon::today()->startOfYear()->toDateString(),
            'employment_type' => 'full_time',
            'employment_status' => 'probation',
            'status' => 'active',
        ]);

        $this->assertGreaterThan(
            0,
            LeaveAllocation::where('employee_id', $result['employee']->id)->count(),
        );
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $existing = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Copy',
            'email' => $existing->email,
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time',
            'employment_status' => 'probation',
            'date_of_joining' => Carbon::today()->toDateString(),
            'notice_period_days' => 30,
            'status' => 'active',
        ])->assertSessionHasErrors('email');
    }

    public function test_an_employee_cannot_report_to_themselves(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->put(route('employees.update', $employee), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'branch_id' => $employee->branch_id,
            'reporting_to' => $employee->id,
            'employment_type' => 'full_time',
            'employment_status' => 'permanent',
            'date_of_joining' => $employee->date_of_joining->toDateString(),
            'notice_period_days' => 30,
            'status' => 'active',
        ])->assertSessionHasErrors('reporting_to');
    }

    public function test_updating_an_employee_keeps_their_login_in_step(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->put(route('employees.update', $employee), [
            'first_name' => 'Renamed',
            'last_name' => 'Person',
            'email' => 'renamed.person@example.test',
            'company_id' => $employee->company_id,
            'branch_id' => $employee->branch_id,
            'employment_type' => 'full_time',
            'employment_status' => 'permanent',
            'date_of_joining' => $employee->date_of_joining->toDateString(),
            'notice_period_days' => 30,
            'status' => 'active',
        ])->assertRedirect();

        $user = $employee->fresh()->user;

        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('renamed.person@example.test', $user->email);
    }

    public function test_offboarding_records_the_exit_and_disables_the_login(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('employees.offboard', $employee), [
            'date_of_exit' => Carbon::today()->toDateString(),
            'employment_status' => 'resigned',
            'exit_reason' => 'Moving to another company.',
        ])->assertRedirect();

        $employee->refresh();

        $this->assertSame('resigned', $employee->employment_status->value);
        $this->assertSame('inactive', $employee->status);
        $this->assertSame('inactive', $employee->user->fresh()->status);
    }

    public function test_a_login_can_be_provisioned_for_an_existing_employee(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $this->seedReferenceData();

        $employee = Employee::create([
            'employee_code' => 'EMP9999',
            'first_name' => 'No',
            'last_name' => 'Login',
            'email' => 'no.login@example.test',
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'date_of_joining' => Carbon::today(),
            'employment_type' => 'full_time',
            'employment_status' => 'probation',
            'status' => 'active',
        ]);

        $this->assertNull($employee->user);

        $this->actingAs($hr->user)
            ->post(route('employees.account', $employee), ['role' => Roles::EMPLOYEE])
            ->assertRedirect();

        $employee->refresh();

        $this->assertNotNull($employee->user);
        $this->assertTrue($employee->user->hasRole(Roles::EMPLOYEE));
        Mail::assertQueued(
            TemplatedMail::class,
            fn ($m) => $m->eventKey === NotificationEvents::EMPLOYEE_WELCOME,
        );
    }

    public function test_an_employee_can_update_their_own_personal_details(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->put(route('profile.personal'), [
            'personal_email' => 'personal@example.test',
            'city' => 'Pune',
            'emergency_contact_name' => 'Next of kin',
            'emergency_contact_phone' => '+91 90000 00000',
        ])->assertRedirect();

        $employee->refresh();

        $this->assertSame('personal@example.test', $employee->personal_email);
        $this->assertSame('Pune', $employee->city);
    }

    public function test_an_employee_cannot_edit_someone_else(): void
    {
        $employee = $this->makeEmployee();
        $colleague = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $employee->branch]);

        $this->actingAs($employee->user)
            ->get(route('employees.edit', $colleague))
            ->assertForbidden();
    }

    public function test_archiving_an_employee_soft_deletes_them(): void
    {
        $hr = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertSame('inactive', User::find($employee->user_id)->status);
    }

    public function test_the_employee_export_returns_a_csv(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $response = $this->actingAs($hr->user)->get(route('employees.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Employee Code', $csv);
        $this->assertStringContainsString($hr->employee_code, $csv);
    }
}
