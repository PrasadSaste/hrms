<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Services\Bank\BankFileService;
use App\Services\PayrollService;
use App\Support\BankFormats;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BankFileTest extends TestCase
{
    use RefreshDatabase;

    protected BankFileService $bank;

    protected PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = app(BankFileService::class);
        $this->payroll = app(PayrollService::class);
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An employee with a salary structure and a bank account. */
    protected function employeeWithSalary(array $attributes = [], float $ctc = 1200000): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2023-01-01'),
            'bank_name' => 'State Bank of India',
            'bank_account_name' => 'A Candidate',
            'bank_account_number' => '3012'.random_int(100000, 999999),
            'bank_ifsc' => 'SBIN0001234',
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->payroll->applyDefaultComponents($structure);
        $this->markFullAttendance($employee);

        return $employee->fresh();
    }

    /**
     * Present on every working day of June 2026.
     *
     * Without it every day is an absence, net pay is zero, and the file would
     * be testing nothing — which is itself the check that a zero net pay is
     * refused, so one test marks nobody.
     */
    protected function markFullAttendance(Employee $employee): void
    {
        $cursor = Carbon::create(2026, 6, 1);
        $end = $cursor->copy()->endOfMonth();
        $attendance = app(\App\Services\AttendanceService::class);

        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                $attendance->record($employee, [
                    'date' => $cursor->toDateString(),
                    'check_in' => '09:30',
                    'check_out' => '18:30',
                ]);
            }
            $cursor->addDay();
        }
    }

    /** An approved run for June 2026, with the company's own account set. */
    protected function approvedRun(array $companyBank = []): Payroll
    {
        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        Company::query()->update(array_merge([
            'bank_name' => 'HDFC Bank',
            'bank_account_name' => 'Beyond Sure Private Limited',
            'bank_account_number' => '50200012345678',
            'bank_ifsc' => 'HDFC0000123',
        ], $companyBank));

        $run->update(['status' => PayrollStatus::Approved]);

        return $run->fresh('company');
    }

    public function test_a_zero_net_pay_cannot_be_transferred(): void
    {
        // Nobody marked present all month, so with unmarked days read as
        // absences there is nothing to pay and nothing to send a bank.
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2023-01-01'),
            'bank_account_number' => '30120000001',
            'bank_ifsc' => 'SBIN0001234',
        ]);

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);
        $this->payroll->applyDefaultComponents($structure);

        $run = $this->approvedRun();

        $this->assertTrue(collect($this->bank->problems($run))->contains(
            fn ($p) => str_contains($p, 'cannot be transferred'),
        ));
    }

    public function test_a_run_that_is_not_approved_has_no_file(): void
    {
        $this->employeeWithSalary();
        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        $this->assertContains(
            'The run has not been approved yet. A bank file is only produced for an approved run.',
            $this->bank->problems($run),
        );
    }

    public function test_a_missing_account_number_is_a_problem_named_after_the_person(): void
    {
        $employee = $this->employeeWithSalary(['bank_account_number' => null]);
        $run = $this->approvedRun();

        $problems = $this->bank->problems($run);

        $this->assertTrue(collect($problems)->contains(
            fn ($p) => str_contains($p, $employee->employee_code) && str_contains($p, 'no bank account number'),
        ), 'Expected the missing account to be reported against '.$employee->employee_code.'. Got: '.implode(' | ', $problems));
    }

    public function test_an_ifsc_that_is_not_one_is_refused(): void
    {
        $this->employeeWithSalary(['bank_ifsc' => 'SBI-1234']);
        $run = $this->approvedRun();

        $this->assertTrue(collect($this->bank->problems($run))->contains(
            fn ($p) => str_contains($p, 'does not look like an IFSC'),
        ));

        $this->assertTrue($this->bank->looksLikeIfsc('SBIN0001234'));
        $this->assertTrue($this->bank->looksLikeIfsc('hdfc0000123'), 'Case should not matter.');
        $this->assertFalse($this->bank->looksLikeIfsc('SBIN1001234'), 'The fifth character is always a zero.');
        $this->assertFalse($this->bank->looksLikeIfsc('SBIN000123'));
        $this->assertFalse($this->bank->looksLikeIfsc(null));
    }

    public function test_two_people_on_one_account_is_reported(): void
    {
        $this->employeeWithSalary(['bank_account_number' => '30129999999']);
        $this->employeeWithSalary(['bank_account_number' => '30129999999']);
        $run = $this->approvedRun();

        $this->assertTrue(collect($this->bank->problems($run))->contains(
            fn ($p) => str_contains($p, 'the same account as'),
        ));
    }

    public function test_a_clean_run_has_no_problems(): void
    {
        $this->employeeWithSalary();
        $this->employeeWithSalary();

        $this->assertSame([], $this->bank->problems($this->approvedRun()));
    }

    public function test_the_payment_type_follows_the_amount_and_the_bank(): void
    {
        // Same bank as the company's account: it never leaves HDFC.
        $internal = $this->employeeWithSalary(['bank_ifsc' => 'HDFC0000999']);
        // Another bank, under two lakh: NEFT.
        $neft = $this->employeeWithSalary(['bank_ifsc' => 'SBIN0001234']);
        // Another bank, two lakh or more: RTGS.
        $rtgs = $this->employeeWithSalary(['bank_ifsc' => 'ICIC0001234'], ctc: 60000000);

        $run = $this->approvedRun();
        $byEmployee = $run->payslips()->get()->keyBy('employee_id');

        $this->assertSame('IFT', $this->bank->transactionType($byEmployee[$internal->id], $run->company));
        $this->assertSame('NEFT', $this->bank->transactionType($byEmployee[$neft->id], $run->company));
        $this->assertGreaterThanOrEqual(BankFormats::RTGS_THRESHOLD, $byEmployee[$rtgs->id]->net_pay);
        $this->assertSame('RTGS', $this->bank->transactionType($byEmployee[$rtgs->id], $run->company));
    }

    public function test_the_generic_file_carries_the_headings_and_an_unformatted_amount(): void
    {
        $employee = $this->employeeWithSalary();
        $run = $this->approvedRun();
        $slip = $run->payslips()->first();

        $contents = $this->bank->contents($run, 'generic', Carbon::parse('2026-06-30'));
        $lines = array_values(array_filter(explode("\n", trim($contents))));

        $this->assertStringContainsString('Beneficiary Name', $lines[0]);
        $this->assertStringContainsString('IFSC', $lines[0]);
        $this->assertCount(2, $lines, 'One heading row and one payment.');

        $row = str_getcsv($lines[1], escape: '');
        $this->assertContains($employee->employee_code, $row);
        $this->assertContains($employee->bank_account_number, $row);
        $this->assertContains('SBIN0001234', $row);
        $this->assertContains('50200012345678', $row, 'The company account the money leaves.');
        $this->assertContains('30/06/2026', $row);
        $this->assertContains(number_format($slip->net_pay, 2, '.', ''), $row);

        // Never the rupee symbol, never a grouped amount: this is the one place
        // a formatted figure would be an error rather than a nicety.
        $this->assertGreaterThan(0, $slip->net_pay);
        $this->assertStringNotContainsString('₹', $contents);
        $this->assertStringNotContainsString(\App\Support\Money::format($slip->net_pay), $contents);
    }

    public function test_a_layout_without_headings_writes_none(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();

        $lines = array_values(array_filter(explode("\n", trim($this->bank->contents($run, 'hdfc')))));

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('NEFT,', $lines[0], 'HDFC leads with the payment type.');
    }

    public function test_the_sbi_layout_is_tab_separated(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();

        $contents = $this->bank->contents($run, 'sbi');

        $this->assertStringContainsString("\t", $contents);
        $this->assertStringNotContainsString(',', $contents);
        $this->assertStringEndsWith('.txt', $this->bank->filename($run, 'sbi'));
    }

    public function test_every_layout_writes_the_same_number_of_columns_as_it_declares(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();

        foreach (BankFormats::all() as $key => $format) {
            $rows = $this->bank->rows($run, $key);

            $this->assertCount(1, $rows, $key.' should write one row per payment.');
            $this->assertCount(count($format['columns']), $rows[0], $key.' wrote the wrong number of columns.');
        }
    }

    public function test_somebody_paid_in_cash_is_left_out_and_counted(): void
    {
        $transfer = $this->employeeWithSalary();
        $cash = $this->employeeWithSalary();
        $cash->salaryStructures()->update(['payment_mode' => 'cash']);

        $run = $this->approvedRun();

        $this->assertSame([$transfer->id], $this->bank->payable($run)->pluck('employee_id')->all());
        $this->assertSame([$cash->id], $this->bank->excluded($run)->pluck('employee_id')->all());
        $this->assertSame(
            round($run->payslips()->where('employee_id', $transfer->id)->first()->net_pay, 2),
            $this->bank->total($run),
        );
    }

    public function test_the_narration_is_stripped_of_anything_a_bank_might_reject(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();

        $narration = $this->bank->narration($run);

        $this->assertSame('Salary Jun 2026', $narration);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9 ]+$/', $narration);
        $this->assertLessThanOrEqual(BankFileService::NARRATION_LIMIT, strlen($narration));
    }

    public function test_the_screen_lists_the_problems_and_withholds_the_button(): void
    {
        $employee = $this->employeeWithSalary(['bank_ifsc' => null]);
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->get(route('payroll.bank-file', $run))
            ->assertOk()
            ->assertSee('Fix these first')
            ->assertSee('no IFSC')
            ->assertSee('Fix the problems first')
            ->assertDontSee('Download the file');
    }

    public function test_a_clean_run_offers_the_download_and_produces_the_file(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->get(route('payroll.bank-file', $run))
            ->assertOk()
            ->assertSee('Download the file')
            ->assertSee('Ready to pay');

        $response = $this->actingAs($accountant->user)
            ->post(route('payroll.bank-file.download', $run), [
                'format' => 'icici',
                'value_date' => '2026-06-30',
            ])
            ->assertOk()
            ->assertDownload('salary-icici-2026-06.csv');

        $this->assertStringContainsString('Beneficiary Ac No', $response->streamedContent());
    }

    public function test_choosing_a_layout_reloads_the_screen_showing_its_columns(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        // Generic writes a Bank column; HDFC does not, and leads with the type.
        $this->actingAs($accountant->user)
            ->get(route('payroll.bank-file', ['payroll' => $run, 'format' => 'generic']))
            ->assertOk()
            ->assertSee('Written with a heading row.');

        $this->actingAs($accountant->user)
            ->get(route('payroll.bank-file', ['payroll' => $run, 'format' => 'sbi']))
            ->assertOk()
            ->assertSee('Tab separated.')
            ->assertSee('Written without a heading row.');

        // A layout nobody has heard of falls back rather than breaking.
        $this->actingAs($accountant->user)
            ->get(route('payroll.bank-file', ['payroll' => $run, 'format' => 'barclays']))
            ->assertOk()
            ->assertSee('Generic CSV');
    }

    public function test_the_chosen_layout_is_remembered_against_the_company(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->post(route('payroll.bank-file.download', $run), ['format' => 'axis'])
            ->assertOk();

        $this->assertSame('axis', $run->company->fresh()->bank_file_format);
        $this->assertSame('axis', $this->bank->formatFor($run->fresh('company')));
    }

    public function test_a_download_is_refused_while_a_problem_stands(): void
    {
        $this->employeeWithSalary(['bank_account_number' => null]);
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->post(route('payroll.bank-file.download', $run), ['format' => 'generic'])
            ->assertRedirect(route('payroll.bank-file', $run))
            ->assertSessionHas('error');
    }

    public function test_a_draft_run_cannot_be_downloaded_at_all(): void
    {
        $this->employeeWithSalary();
        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->post(route('payroll.bank-file.download', $run), ['format' => 'generic'])
            ->assertForbidden();
    }

    public function test_an_employee_cannot_reach_the_bank_file(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->get(route('payroll.bank-file', $run))
            ->assertForbidden();

        $this->actingAs($employee->user)
            ->post(route('payroll.bank-file.download', $run), ['format' => 'generic'])
            ->assertForbidden();
    }

    public function test_an_unknown_layout_is_refused(): void
    {
        $this->employeeWithSalary();
        $run = $this->approvedRun();
        $accountant = $this->makeEmployee(Roles::ACCOUNTANT);

        $this->actingAs($accountant->user)
            ->post(route('payroll.bank-file.download', $run), ['format' => 'barclays'])
            ->assertSessionHasErrors('format');
    }
}
