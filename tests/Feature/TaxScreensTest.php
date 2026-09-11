<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\TaxDeclaration;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Services\TaxDeclarationService;
use App\Services\TaxStatementService;
use App\Support\FinancialYear;
use App\Support\Roles;
use App\Support\TaxRegimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaxScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function employeeOnSalary(string $role = Roles::EMPLOYEE, float $ctc = 2400000): Employee
    {
        $employee = $this->makeEmployee($role, [
            'date_of_joining' => Carbon::parse('2020-01-01'),
            'date_of_birth' => Carbon::parse('1990-01-01'),
        ]);

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2020-01-01',
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        app(PayrollService::class)->applyDefaultComponents($structure);

        return $employee->fresh();
    }

    protected function declarationFor(Employee $employee, array $amounts = ['80c' => 150000]): TaxDeclaration
    {
        $service = app(TaxDeclarationService::class);
        $declaration = $service->forEmployee($employee, FinancialYear::current());

        return $service->save($declaration, $amounts, [
            'regime' => TaxRegimes::OLD,
            'metro' => false,
        ]);
    }

    // ------------------------------------------------------------- my own

    public function test_an_employee_sees_their_own_screen_and_both_regimes(): void
    {
        $employee = $this->employeeOnSalary();

        $this->actingAs($employee->user)
            ->get(route('tax.mine'))
            ->assertOk()
            ->assertSee('Income tax')
            ->assertSee('New regime')
            ->assertSee('Old regime')
            ->assertSee('Section 80C');
    }

    public function test_an_employee_can_save_and_then_hand_in(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = app(TaxDeclarationService::class)->forEmployee($employee, FinancialYear::current());

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration), [
                'regime' => TaxRegimes::OLD,
                'metro' => '1',
                'amounts' => ['80c' => '150000', 'hra' => '240000'],
            ])
            ->assertRedirect();

        $declaration->refresh()->load('items');
        $this->assertSame(TaxRegimes::OLD, $declaration->regime);
        $this->assertTrue($declaration->metro);
        $this->assertSame(TaxDeclaration::DRAFT, $declaration->status);
        $this->assertCount(2, $declaration->items);

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration), [
                'regime' => TaxRegimes::OLD,
                'amounts' => ['80c' => '150000'],
                'submit' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(TaxDeclaration::SUBMITTED, $declaration->fresh()->status);
    }

    public function test_a_blank_box_removes_the_section_rather_than_storing_a_nought(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee, ['80c' => 150000, '80e' => 20000]);

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration), [
                'regime' => TaxRegimes::OLD,
                'amounts' => ['80c' => '150000', '80e' => ''],
            ])
            ->assertRedirect();

        $sections = $declaration->fresh('items')->items->pluck('section')->all();
        $this->assertSame(['80c'], $sections);
    }

    public function test_a_handed_in_declaration_cannot_be_changed(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);
        app(TaxDeclarationService::class)->submit($declaration);

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration->fresh()), [
                'regime' => TaxRegimes::NEW,
                'amounts' => ['80c' => '10'],
            ])
            ->assertForbidden();

        $this->assertSame(TaxRegimes::OLD, $declaration->fresh()->regime);
    }

    public function test_a_section_the_catalogue_does_not_know_is_dropped_not_refused(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee, []);

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration), [
                'regime' => TaxRegimes::OLD,
                'amounts' => ['80c' => '150000', 'section80zz' => '999999'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['80c'], $declaration->fresh('items')->items->pluck('section')->all());
    }

    // --------------------------------------------------------- verification

    public function test_hr_sees_the_list_and_can_record_what_they_accepted(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);
        app(TaxDeclarationService::class)->submit($declaration);
        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);

        $this->actingAs($hr->user)
            ->get(route('tax.index'))
            ->assertOk()
            ->assertSee($employee->employee_code);

        $this->actingAs($hr->user)
            ->get(route('tax.show', $declaration))
            ->assertOk()
            ->assertSee('Section 80C');

        $this->actingAs($hr->user)
            ->post(route('tax.verify', $declaration), [
                'verified' => ['80c' => '90000'],
                'remarks' => 'The premium receipt covers 90,000 only.',
            ])
            ->assertRedirect();

        $declaration->refresh()->load('items');
        $this->assertSame(TaxDeclaration::VERIFIED, $declaration->status);
        $this->assertSame(90000.0, $declaration->items->first()->verified_amount);
        $this->assertSame(150000.0, $declaration->items->first()->declared_amount);
        $this->assertSame($hr->user->id, $declaration->verified_by);
    }

    public function test_the_status_chips_add_up_to_the_table(): void
    {
        $drafting = $this->employeeOnSalary();
        $this->declarationFor($drafting);

        $handedIn = $this->employeeOnSalary();
        app(TaxDeclarationService::class)->submit($this->declarationFor($handedIn));

        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);

        $response = $this->actingAs($hr->user)->get(route('tax.index'))->assertOk();
        $counts = $response->viewData('counts');

        $this->assertSame(1, $counts[TaxDeclaration::DRAFT]);
        $this->assertSame(1, $counts[TaxDeclaration::SUBMITTED]);
        $this->assertSame(0, $counts[TaxDeclaration::VERIFIED]);
        $this->assertSame(2, array_sum($counts), 'The chips have to add up to the table.');

        $this->actingAs($hr->user)
            ->get(route('tax.index', ['status' => TaxDeclaration::SUBMITTED]))
            ->assertOk()
            ->assertSee($handedIn->employee_code)
            ->assertDontSee($drafting->employee_code);
    }

    public function test_hr_can_send_it_back_and_the_employee_can_edit_again(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);
        app(TaxDeclarationService::class)->submit($declaration);
        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);

        $this->actingAs($hr->user)
            ->post(route('tax.send-back', $declaration), ['remarks' => 'No proof attached for 80C.'])
            ->assertRedirect();

        $this->assertSame(TaxDeclaration::RETURNED, $declaration->fresh()->status);

        $this->actingAs($employee->user)
            ->put(route('tax.update', $declaration->fresh()), [
                'regime' => TaxRegimes::OLD,
                'amounts' => ['80c' => '120000'],
            ])
            ->assertRedirect();

        $this->assertSame(120000.0, $declaration->fresh('items')->items->first()->declared_amount);
    }

    public function test_sending_back_needs_a_reason(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);
        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);

        $this->actingAs($hr->user)
            ->post(route('tax.send-back', $declaration), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
    }

    public function test_nobody_verifies_their_own_declaration(): void
    {
        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);
        $declaration = $this->declarationFor($hr);

        $this->actingAs($hr->user)
            ->post(route('tax.verify', $declaration), ['verified' => ['80c' => '0']])
            ->assertForbidden();
    }

    public function test_an_employee_cannot_see_anybody_elses(): void
    {
        $employee = $this->employeeOnSalary();
        $other = $this->employeeOnSalary();
        $declaration = $this->declarationFor($other);

        $this->actingAs($employee->user)->get(route('tax.index'))->assertForbidden();
        $this->actingAs($employee->user)->get(route('tax.show', $declaration))->assertForbidden();
        $this->actingAs($employee->user)
            ->get(route('tax.computation', $other))
            ->assertForbidden();
    }

    public function test_an_employee_can_see_their_own_computation(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declarationFor($employee);

        $this->actingAs($employee->user)
            ->get(route('tax.computation', $employee))
            ->assertOk()
            ->assertSee('Taxable income')
            ->assertSee('Health and education cess');
    }

    // ---------------------------------------------------------------- proofs

    public function test_a_proof_is_stored_privately_and_streamed(): void
    {
        Storage::fake('local');

        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);

        $this->actingAs($employee->user)
            ->post(route('tax.proof.upload', $declaration), [
                'section' => '80c',
                'proof' => UploadedFile::fake()->create('premium.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $item = $declaration->fresh('items')->items->firstWhere('section', '80c');
        $this->assertNotNull($item->proof_path);
        Storage::disk('local')->assertExists($item->proof_path);

        // Never on the public disk, and never reachable without going through
        // the controller that checks who is asking.
        $this->assertStringNotContainsString('public', $item->proof_path);

        $this->actingAs($employee->user)
            ->get(route('tax.proof', $item))
            ->assertOk();

        $stranger = $this->employeeOnSalary();
        $this->actingAs($stranger->user)
            ->get(route('tax.proof', $item))
            ->assertForbidden();
    }

    public function test_clearing_a_figure_keeps_a_proof_hr_has_already_ruled_on(): void
    {
        Storage::fake('local');

        $employee = $this->employeeOnSalary();
        $declaration = $this->declarationFor($employee);
        $service = app(TaxDeclarationService::class);
        $service->attachProof($declaration, '80c', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));

        $hr = $this->employeeOnSalary(Roles::HR_MANAGER);
        $service->verify($declaration->fresh('items'), ['80c' => '90000'], $hr->user);

        $service->save($declaration->fresh('items'), ['80c' => 0], []);

        $item = $declaration->fresh('items')->items->firstWhere('section', '80c');
        $this->assertNotNull($item, 'A verified item survives the figure being cleared.');
        $this->assertNotNull($item->proof_path);
    }

    // ------------------------------------------------------------- statement

    public function test_the_statement_is_a_pdf_that_says_it_is_not_a_form_16(): void
    {
        $employee = $this->employeeOnSalary();
        Setting::put('payroll_tds_enabled', true, 'payroll');

        $attendance = app(AttendanceService::class);
        $cursor = Carbon::create(2026, 6, 1);
        while ($cursor->lte(Carbon::create(2026, 6, 30))) {
            if ($cursor->isoWeekday() <= 5) {
                $attendance->record($employee, [
                    'date' => $cursor->toDateString(),
                    'check_in' => '09:30',
                    'check_out' => '18:30',
                ]);
            }
            $cursor->addDay();
        }

        $payroll = app(PayrollService::class);
        $run = $payroll->createRun(['month' => 6, 'year' => 2026]);
        $payroll->generate($run);

        $gathered = app(TaxStatementService::class)->gather($employee, 2026);

        $this->assertSame(1, $gathered['months']);
        $this->assertGreaterThan(0, $gathered['tax_deducted']);
        $this->assertSame('2027–28', $gathered['assessment_year']);
        $this->assertNotEmpty($gathered['earnings']);

        $response = $this->actingAs($employee->user)
            ->get(route('tax.statement', ['employee' => $employee, 'year' => 2026]))
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());

        // The disclaimer is on the face of the document, not in a footnote.
        $html = view('pdf.tax-statement', $gathered + [
            'company' => app(\App\Services\Letterhead::class)->forCompany($employee->company),
        ])->render();

        $this->assertStringContainsString('This is not a Form 16', $html);
        $this->assertStringContainsString('TRACES', $html);
    }

    public function test_a_stranger_cannot_download_somebody_elses_statement(): void
    {
        $employee = $this->employeeOnSalary();
        $stranger = $this->employeeOnSalary();

        $this->actingAs($stranger->user)
            ->get(route('tax.statement', ['employee' => $employee, 'year' => 2026]))
            ->assertForbidden();
    }
}
