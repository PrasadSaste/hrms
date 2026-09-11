<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Services\PayrollService;
use App\Services\PayslipPdfService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A group runs several legal entities. Payroll must never mix them, and a
 * salary slip must say who actually paid it.
 */
class MultiCompanyPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        $this->payroll = app(PayrollService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------- the setup

    public function test_an_employee_is_given_a_company_even_when_nobody_picks_one(): void
    {
        $employee = $this->makeEmployee();

        $this->assertNotNull(
            $employee->company_id,
            'An employee with no company would be silently left out of payroll.',
        );
    }

    public function test_hr_chooses_the_employing_company_when_onboarding(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL');

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Nadia', 'last_name' => 'Rahman',
            'email' => 'nadia.rahman@example.test',
            'company_id' => $second->id,
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time', 'employment_status' => 'probation',
            'date_of_joining' => now()->toDateString(), 'notice_period_days' => 30,
            'status' => 'active', 'create_account' => '0', 'send_welcome_email' => '0',
        ])->assertRedirect();

        $joiner = Employee::where('email', 'nadia.rahman@example.test')->firstOrFail();

        $this->assertSame($second->id, $joiner->company_id);
    }

    public function test_the_employing_company_is_required(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Nobody', 'last_name' => 'Home',
            'email' => 'nobody.home@example.test',
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time', 'employment_status' => 'probation',
            'date_of_joining' => now()->toDateString(), 'notice_period_days' => 30,
            'status' => 'active',
        ])->assertSessionHasErrors('company_id');
    }

    // ------------------------------------------------------------ separation

    public function test_a_run_pays_only_its_own_company(): void
    {
        $first = $this->defaultCompany();
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL');

        $ours = $this->employeeWithSalary(['company_id' => $first->id]);
        $theirs = $this->employeeWithSalary(['company_id' => $second->id]);

        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $first->id]);
        $result = $this->payroll->generate($run);

        $this->assertSame(1, $result['generated']);
        $this->assertTrue($run->payslips()->where('employee_id', $ours->id)->exists());
        $this->assertFalse(
            $run->payslips()->where('employee_id', $theirs->id)->exists(),
            'Payroll for one entity picked up another entity\'s employee.',
        );
    }

    public function test_both_companies_can_run_the_same_month(): void
    {
        $first = $this->defaultCompany();
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL');

        $this->employeeWithSalary(['company_id' => $first->id]);
        $this->employeeWithSalary(['company_id' => $second->id]);

        $runOne = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $first->id]);
        $runTwo = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $second->id]);

        $this->assertSame(1, $this->payroll->generate($runOne)['generated']);
        $this->assertSame(1, $this->payroll->generate($runTwo)['generated']);
        $this->assertNotSame($runOne->id, $runTwo->id);
    }

    public function test_the_same_company_cannot_run_the_same_month_twice(): void
    {
        $company = $this->defaultCompany();
        $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $company->id]);

        $this->expectException(ValidationException::class);
        $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $company->id]);
    }

    public function test_an_employee_with_no_company_still_gets_paid_by_the_default_run(): void
    {
        $company = $this->defaultCompany();
        $stray = $this->employeeWithSalary();

        // Somebody imported before companies existed.
        Employee::whereKey($stray->id)->update(['company_id' => null]);

        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $company->id]);

        $this->assertSame(1, $this->payroll->generate($run)['generated']);
    }

    // ---------------------------------------------------------- the pay slip

    public function test_a_slip_records_the_company_that_issued_it(): void
    {
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL', ['payslip_prefix' => 'SIBL']);
        $employee = $this->employeeWithSalary(['company_id' => $second->id]);

        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $second->id]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame($second->id, $payslip->company_id);
        $this->assertStringStartsWith('SIBL-', $payslip->slip_number);
    }

    public function test_moving_someone_between_companies_does_not_rewrite_old_slips(): void
    {
        $first = $this->defaultCompany();
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL');
        $employee = $this->employeeWithSalary(['company_id' => $first->id]);

        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $first->id]);
        $this->payroll->generate($run);
        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $employee->update(['company_id' => $second->id]);

        $this->assertSame(
            $first->id,
            $payslip->fresh()->company_id,
            'A transfer rewrote who paid an earlier month.',
        );
    }

    public function test_the_pdf_carries_its_own_company_details(): void
    {
        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL', [
            'legal_name' => 'Shrigoda Insurance Brokers Limited',
            'registration_number' => 'U66000MH2020PLC000000',
            'pf_number' => 'PF-SIBL-1',
            'signatory_name' => 'A Director',
            'signatory_designation' => 'Director',
        ]);
        $employee = $this->employeeWithSalary(['company_id' => $second->id]);

        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026, 'company_id' => $second->id]);
        $this->payroll->generate($run);
        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();

        $details = app(PayslipPdfService::class)->companyDetails($payslip->company);

        $this->assertSame('Shrigoda Insurance Brokers Limited', $details['name']);
        $this->assertSame('U66000MH2020PLC000000', $details['registration_number']);
        $this->assertSame('PF-SIBL-1', $details['pf_number']);
        $this->assertSame('A Director', $details['signatory_name']);
    }

    public function test_an_old_slip_without_a_company_still_prints(): void
    {
        $employee = $this->employeeWithSalary();
        $run = $this->payroll->createRun(['month' => 5, 'year' => 2026]);
        $this->payroll->generate($run);

        $payslip = Payslip::where('employee_id', $employee->id)->firstOrFail();
        Payslip::whereKey($payslip->id)->update(['company_id' => null]);

        $details = app(PayslipPdfService::class)->companyDetails($payslip->fresh()->company);

        $this->assertNotEmpty($details['name'], 'A slip predating companies lost its letterhead.');
    }

    // ------------------------------------------------------------ the screens

    public function test_only_a_manager_can_change_the_companies(): void
    {
        $employee = $this->makeEmployee();
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($employee->user)->get(route('companies.index'))->assertForbidden();
        $this->actingAs($admin->user)->get(route('companies.index'))->assertOk();

        $this->actingAs($admin->user)->post(route('companies.store'), [
            'name' => 'Third Entity', 'code' => 'TE',
            'currency' => 'INR', 'payslip_prefix' => 'TE', 'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', ['code' => 'TE']);
    }

    public function test_a_company_that_has_paid_people_cannot_be_deleted(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->defaultCompany();

        $this->actingAs($admin->user)
            ->delete(route('companies.destroy', $company))
            ->assertRedirect();

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_only_one_company_is_ever_the_default(): void
    {
        $first = $this->defaultCompany();
        $this->assertTrue($first->fresh()->is_default);

        $second = $this->makeCompany('Shrigoda Insurance Brokers', 'SIBL', ['is_default' => true]);

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame($second->id, Company::default()->id);
    }

    /** A second payroll entity. */
    protected function makeCompany(string $name, string $code, array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => $name,
            'code' => $code,
            'currency' => 'INR',
            'payslip_prefix' => $code,
            'status' => 'active',
        ], $attributes));
    }

    /** An employee on a salary structure, so payroll has something to pay. */
    protected function employeeWithSalary(array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        app(PayrollService::class)->applyDefaultComponents($structure);

        return $employee->fresh();
    }
}
