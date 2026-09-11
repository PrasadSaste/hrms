<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Services\Letterhead;
use App\Services\LetterPdfService;
use App\Services\LetterService;
use App\Services\PayrollService;
use App\Services\PayslipPdfService;
use App\Support\LetterTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The pad every generated document is printed on, and the mark across it.
 */
class DocumentPadTest extends TestCase
{
    use RefreshDatabase;

    protected Letterhead $letterhead;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 10:00:00'));
        $this->letterhead = app(Letterhead::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    protected function employeeWithSalary(array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ], $attributes));

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'gross_monthly' => 96000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        return $employee->fresh(['company']);
    }

    // ------------------------------------------------------------- the logo

    public function test_a_company_prints_its_own_letterhead_logo(): void
    {
        Storage::fake('public');
        $company = $this->makeCompany('Shrigoda', 'SIBL', [
            'logo_path' => UploadedFile::fake()->image('screen.png', 200, 60)->store('companies', 'public'),
            'letterhead_logo_path' => UploadedFile::fake()->image('print.png', 600, 180)->store('companies', 'public'),
        ]);

        $pad = $this->letterhead->forCompany($company);

        $this->assertSame($company->letterhead_logo_path, $pad['logo']);
        $this->assertStringStartsWith('data:image/', $pad['logo_src']);
    }

    public function test_without_a_print_logo_the_screen_one_is_used(): void
    {
        Storage::fake('public');
        $company = $this->makeCompany('Shrigoda', 'SIBL', [
            'logo_path' => UploadedFile::fake()->image('screen.png', 200, 60)->store('companies', 'public'),
        ]);

        $this->assertSame($company->logo_path, $this->letterhead->forCompany($company)['logo']);
    }

    public function test_one_company_never_prints_another_companys_logo(): void
    {
        Storage::fake('public');
        // The group logo from Settings belongs to the group, not to every
        // entity: a second employer printing it would misstate who issued the
        // document.
        Setting::put('company_logo', UploadedFile::fake()->image('group.png', 200, 60)->store('branding', 'public'), 'branding');

        $company = $this->makeCompany('Shrigoda', 'SIBL');

        $pad = $this->letterhead->forCompany($company);

        $this->assertNull($pad['logo']);
        $this->assertNull($pad['logo_src']);
        $this->assertSame('Shrigoda', $pad['name'], 'A company with no logo must still be named.');
    }

    public function test_a_document_with_no_company_at_all_still_has_a_pad(): void
    {
        Setting::put('company_name', 'Beyond Sure', 'company');

        $pad = $this->letterhead->forCompany(null);

        $this->assertSame('Beyond Sure', $pad['name']);
        $this->assertSame('Beyond Sure', $pad['watermark']);
    }

    // -------------------------------------------------------- the watermark

    public function test_the_watermark_defaults_to_the_company_name(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL');

        $this->assertSame('Shrigoda', $company->watermark());
        $this->assertSame('Shrigoda', $this->letterhead->forCompany($company)['watermark']);
    }

    public function test_the_watermark_wording_can_be_set(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL', ['watermark_text' => 'Confidential']);

        $this->assertSame('Confidential', $company->watermark());
    }

    public function test_the_watermark_can_be_switched_off(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL', ['watermark_enabled' => false]);

        $this->assertNull($company->watermark());
        $this->assertNull($this->letterhead->forCompany($company)['watermark']);
    }

    public function test_the_watermark_is_a_pale_step_of_the_theme(): void
    {
        Setting::put('brand_color', '#2563eb', 'branding');

        $pad = $this->letterhead->forCompany($this->makeCompany('Shrigoda', 'SIBL'));

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $pad['watermark_color']);
        $this->assertNotSame('#2563eb', $pad['watermark_color'], 'The mark must be paler than the theme itself.');
    }

    // ------------------------------------------------------- both documents

    public function test_a_salary_slip_and_a_letter_share_the_same_pad(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL', [
            'legal_name' => 'Shrigoda Insurance Brokers Limited',
            'registration_number' => 'U66000MH2020PLC000000',
            'letterhead_footer' => 'Registered office: 4th Floor, Nariman Point, Mumbai 400021',
            'watermark_text' => 'Shrigoda Copy',
        ]);
        $employee = $this->employeeWithSalary(['company_id' => $company->id]);

        $run = app(PayrollService::class)->createRun(['month' => 5, 'year' => 2026, 'company_id' => $company->id]);
        app(PayrollService::class)->generate($run);
        $payslip = $employee->payslips()->firstOrFail();

        $letter = app(LetterService::class)->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        foreach ([
            app(PayslipPdfService::class)->make($payslip)->output(),
            app(LetterPdfService::class)->make($letter)->output(),
        ] as $pdf) {
            $this->assertNotEmpty($pdf);
            // Both are rendered, both non-trivial: the pad partial is shared,
            // so a change to it that breaks one breaks the other.
            $this->assertGreaterThan(1000, strlen($pdf));
        }

        // The rendered HTML is what carries the pad, so check that directly.
        $html = view('pdf.payslip', [
            'payslip' => $payslip,
            'employee' => $employee,
            'company' => $this->letterhead->forCompany($company),
        ])->render();

        $this->assertStringContainsString('Shrigoda Insurance Brokers Limited', $html);
        $this->assertStringContainsString('U66000MH2020PLC000000', $html);
        $this->assertStringContainsString('Registered office: 4th Floor, Nariman Point, Mumbai 400021', $html);
        $this->assertStringContainsString('SHRIGODA COPY', $html);
    }

    public function test_a_letter_carries_the_footer_and_the_watermark_too(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL', [
            'letterhead_footer' => 'CIN U66000MH2020PLC000000',
            'watermark_text' => 'Original',
        ]);
        $employee = $this->employeeWithSalary(['company_id' => $company->id]);
        $letter = app(LetterService::class)->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $html = view('pdf.letter', [
            'letter' => $letter,
            'employee' => $employee,
            'company' => $this->letterhead->forCompany($company),
            'body' => $letter->html(),
            'signature' => null,
            'acknowledgement' => false,
        ])->render();

        $this->assertStringContainsString('CIN U66000MH2020PLC000000', $html);
        $this->assertStringContainsString('ORIGINAL', $html);
        $this->assertStringContainsString($letter->reference, $html);
    }

    public function test_a_company_with_the_watermark_off_prints_none(): void
    {
        $company = $this->makeCompany('Shrigoda', 'SIBL', ['watermark_enabled' => false]);
        $employee = $this->employeeWithSalary(['company_id' => $company->id]);
        $letter = app(LetterService::class)->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $html = view('pdf.letter', [
            'letter' => $letter,
            'employee' => $employee,
            'company' => $this->letterhead->forCompany($company),
            'body' => $letter->html(),
            'signature' => null,
            'acknowledgement' => false,
        ])->render();

        $this->assertStringNotContainsString('class="watermark"', $html);
    }

    // ----------------------------------------------------------- the screen

    public function test_an_administrator_can_set_the_pad(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->makeCompany('Shrigoda', 'SIBL');

        $this->actingAs($admin->user)
            ->put(route('companies.update', $company), $this->payload([
                'letterhead_logo' => UploadedFile::fake()->image('print.png', 600, 180),
                'letterhead_footer' => 'Registered office: Nariman Point, Mumbai',
                'watermark_enabled' => '1',
                'watermark_text' => 'Shrigoda Copy',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $company->refresh();

        $this->assertNotNull($company->letterhead_logo_path);
        Storage::disk('public')->assertExists($company->letterhead_logo_path);
        $this->assertSame('Registered office: Nariman Point, Mumbai', $company->letterhead_footer);
        $this->assertSame('Shrigoda Copy', $company->watermark_text);
        $this->assertTrue($company->watermark_enabled);
    }

    public function test_the_watermark_is_turned_off_by_leaving_the_box_clear(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->makeCompany('Shrigoda', 'SIBL');

        $this->actingAs($admin->user)
            ->put(route('companies.update', $company), $this->payload())
            ->assertRedirect();

        $this->assertFalse($company->fresh()->watermark_enabled);
    }

    public function test_an_svg_is_refused_as_a_print_logo(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->makeCompany('Shrigoda', 'SIBL');

        // The PDF renderer cannot rasterise SVG, so accepting one would mean a
        // company whose documents silently print no logo at all.
        $this->actingAs($admin->user)
            ->put(route('companies.update', $company), $this->payload([
                'letterhead_logo' => UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
            ]))
            ->assertSessionHasErrors('letterhead_logo');
    }

    public function test_the_print_logo_can_be_removed_again(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->makeCompany('Shrigoda', 'SIBL', [
            'letterhead_logo_path' => UploadedFile::fake()->image('print.png', 600, 180)->store('companies', 'public'),
        ]);
        $stored = $company->letterhead_logo_path;

        $this->actingAs($admin->user)
            ->put(route('companies.update', $company), $this->payload(['remove_letterhead_logo' => '1']))
            ->assertRedirect();

        $this->assertNull($company->fresh()->letterhead_logo_path);
        Storage::disk('public')->assertMissing($stored);
    }

    /** The company form posts every field, so a partial update is not a wipe. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Shrigoda',
            'code' => 'SIBL',
            'currency' => 'INR',
            'payslip_prefix' => 'SIBL',
            'status' => 'active',
        ], $overrides);
    }
}
