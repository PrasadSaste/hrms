<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Letter;
use App\Models\SalaryStructure;
use App\Models\Signatory;
use App\Services\Letterhead;
use App\Services\LetterPdfService;
use App\Services\LetterService;
use App\Support\LetterTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Who signs for a company: the list, the default, and what a letter freezes.
 */
class SignatoryTest extends TestCase
{
    use RefreshDatabase;

    protected LetterService $letters;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 10:00:00'));
        $this->letters = app(LetterService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function signatory(Company $company, array $attributes = []): Signatory
    {
        return $company->signatories()->create(array_merge([
            'name' => 'Anita Rao',
            'designation' => 'Director',
            'status' => 'active',
        ], $attributes));
    }

    protected function paidEmployee(array $attributes = [])
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => '2023-04-01',
        ], $attributes));

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2026-04-01',
            'ctc_annual' => 900000,
            'basic_salary' => 30000,
            'gross_monthly' => 72000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        return $employee->fresh(['company']);
    }

    // ----------------------------------------------------------- the list

    public function test_only_one_signatory_is_the_default(): void
    {
        $company = $this->defaultCompany();

        $first = $this->signatory($company, ['name' => 'Anita Rao', 'is_default' => true]);
        $second = $this->signatory($company, ['name' => 'Bhavna Shah', 'is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue($company->defaultSignatory()->is($second));
    }

    public function test_another_company_keeps_its_own_default(): void
    {
        $ours = $this->defaultCompany();
        $theirs = Company::create([
            'name' => 'Other Co', 'code' => 'OTHER', 'currency' => 'INR',
            'payslip_prefix' => 'OC', 'status' => 'active',
        ]);

        $mine = $this->signatory($ours, ['is_default' => true]);
        $this->signatory($theirs, ['name' => 'Ravi Menon', 'is_default' => true]);

        $this->assertTrue($mine->fresh()->is_default, 'Marking a default elsewhere demoted ours.');
    }

    public function test_a_retired_signatory_is_not_offered(): void
    {
        $company = $this->defaultCompany();

        $this->signatory($company, ['name' => 'Anita Rao', 'status' => 'inactive']);
        $active = $this->signatory($company, ['name' => 'Bhavna Shah']);

        $this->assertTrue($company->defaultSignatory()->is($active));
    }

    public function test_a_company_that_predates_the_list_still_prints_its_single_name(): void
    {
        $company = $this->defaultCompany();
        $company->update(['signatory_name' => 'Old Name', 'signatory_designation' => 'Director']);

        $details = app(Letterhead::class)->forCompany($company->fresh());

        $this->assertSame('Old Name', $details['signatory_name']);
        $this->assertSame('Director', $details['signatory_designation']);
    }

    public function test_the_letterhead_prefers_the_list_over_the_old_single_name(): void
    {
        $company = $this->defaultCompany();
        $company->update(['signatory_name' => 'Old Name', 'signatory_designation' => 'Director']);
        $this->signatory($company, ['name' => 'Anita Rao', 'designation' => 'Managing Director', 'is_default' => true]);

        $details = app(Letterhead::class)->forCompany($company->fresh());

        $this->assertSame('Anita Rao', $details['signatory_name']);
        $this->assertSame('Managing Director', $details['signatory_designation']);
    }

    // -------------------------------------------------------- the letters

    public function test_a_letter_goes_out_over_the_company_default(): void
    {
        $employee = $this->paidEmployee();
        $this->signatory($employee->company, ['name' => 'Anita Rao', 'designation' => 'Director', 'is_default' => true]);

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->assertSame('Anita Rao', $letter->signatory_name);
        $this->assertSame('Director', $letter->signatory_designation);
    }

    public function test_the_issuer_may_choose_somebody_else(): void
    {
        $employee = $this->paidEmployee();
        $this->signatory($employee->company, ['name' => 'Anita Rao', 'is_default' => true]);
        $stand_in = $this->signatory($employee->company, ['name' => 'Bhavna Shah', 'designation' => 'Company Secretary']);

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, [], null, $stand_in);

        $this->assertSame('Bhavna Shah', $letter->signatory_name);
        $this->assertSame('Company Secretary', $letter->signatory_designation);
        $this->assertTrue($letter->signatory->is($stand_in));
    }

    public function test_a_signatory_of_another_company_is_refused(): void
    {
        $employee = $this->paidEmployee();
        $theirs = Company::create([
            'name' => 'Other Co', 'code' => 'OTHER', 'currency' => 'INR',
            'payslip_prefix' => 'OC', 'status' => 'active',
        ]);
        $outsider = $this->signatory($theirs, ['name' => 'Ravi Menon']);

        $this->expectException(ValidationException::class);

        $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, [], null, $outsider);
    }

    public function test_the_name_on_an_issued_letter_never_changes_again(): void
    {
        $employee = $this->paidEmployee();
        $signatory = $this->signatory($employee->company, ['name' => 'Anita Rao', 'is_default' => true]);

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $signatory->update(['name' => 'Bhavna Shah', 'designation' => 'Company Secretary', 'status' => 'inactive']);

        $this->assertSame('Anita Rao', $letter->fresh()->signatory_name);
        $this->assertSame('Director', $letter->fresh()->signatory_designation);
    }

    public function test_a_letter_with_nobody_to_sign_it_still_issues(): void
    {
        $employee = $this->paidEmployee();

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->assertNull($letter->signatory_id);
        $this->assertNull($letter->signatory_name);
        $this->assertNotEmpty(app(LetterPdfService::class)->bytes($letter));
    }

    public function test_the_issue_screen_offers_the_company_signatories(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee();
        $this->signatory($employee->company, ['name' => 'Anita Rao', 'is_default' => true]);
        $this->signatory($employee->company, ['name' => 'Bhavna Shah']);

        $this->actingAs($hr->user)
            ->get(route('letters.create', ['employee_id' => $employee->id, 'type' => LetterTypes::EMPLOYMENT_PROOF]))
            ->assertOk()
            ->assertSee('Signed by')
            ->assertSee('Anita Rao')
            ->assertSee('Bhavna Shah');
    }

    public function test_issuing_over_a_chosen_name_from_the_screen(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee();
        $this->signatory($employee->company, ['name' => 'Anita Rao', 'is_default' => true]);
        $chosen = $this->signatory($employee->company, ['name' => 'Bhavna Shah']);

        $this->actingAs($hr->user)->post(route('letters.store'), [
            'employee_id' => $employee->id,
            'type' => LetterTypes::EMPLOYMENT_PROOF,
            'signatory_id' => $chosen->id,
        ])->assertRedirect();

        $this->assertSame('Bhavna Shah', Letter::where('employee_id', $employee->id)->latest('id')->first()->signatory_name);
    }

    // --------------------------------------------------------- the screen

    public function test_the_first_signatory_added_becomes_the_default(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->defaultCompany();

        $this->actingAs($admin->user)
            ->post(route('companies.signatories.store', $company), [
                'name' => 'Anita Rao',
                'designation' => 'Director',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertTrue($company->fresh()->defaultSignatory()->is_default);
    }

    public function test_editing_somebody_does_not_clear_who_signs_by_default(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->defaultCompany();
        $signatory = $this->signatory($company, ['is_default' => true]);

        $this->actingAs($admin->user)
            ->put(route('companies.signatories.update', [$company, $signatory]), [
                'name' => 'Anita Rao',
                'designation' => 'Managing Director',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertTrue($signatory->fresh()->is_default);
        $this->assertSame('Managing Director', $signatory->fresh()->designation);
    }

    public function test_retiring_somebody_takes_the_default_away_from_them(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->defaultCompany();
        $signatory = $this->signatory($company, ['is_default' => true]);

        $this->actingAs($admin->user)
            ->put(route('companies.signatories.update', [$company, $signatory]), [
                'name' => 'Anita Rao',
                'status' => 'inactive',
            ])->assertRedirect();

        $this->assertFalse($signatory->fresh()->is_default);
        $this->assertNull($company->fresh()->defaultSignatory());
    }

    public function test_somebody_who_has_signed_is_retired_rather_than_deleted(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->paidEmployee();
        $company = $employee->company;
        $signatory = $this->signatory($company, ['is_default' => true]);

        $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->actingAs($admin->user)
            ->delete(route('companies.signatories.destroy', [$company, $signatory]))
            ->assertRedirect();

        $this->assertDatabaseHas('signatories', ['id' => $signatory->id, 'status' => 'inactive']);
    }

    public function test_somebody_who_has_signed_nothing_is_removed(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = $this->defaultCompany();
        $signatory = $this->signatory($company);

        $this->actingAs($admin->user)
            ->delete(route('companies.signatories.destroy', [$company, $signatory]))
            ->assertRedirect();

        $this->assertDatabaseMissing('signatories', ['id' => $signatory->id]);
    }

    public function test_a_signatory_of_another_company_is_not_reachable_through_this_one(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $ours = $this->defaultCompany();
        $theirs = Company::create([
            'name' => 'Other Co', 'code' => 'OTHER', 'currency' => 'INR',
            'payslip_prefix' => 'OC', 'status' => 'active',
        ]);
        $outsider = $this->signatory($theirs, ['name' => 'Ravi Menon']);

        $this->actingAs($admin->user)
            ->delete(route('companies.signatories.destroy', [$ours, $outsider]))
            ->assertNotFound();
    }

    public function test_an_ordinary_employee_cannot_see_the_list(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->get(route('companies.signatories.index', $this->defaultCompany()))
            ->assertForbidden();
    }

    // ------------------------------------------------------ the signature

    public function test_a_specimen_signature_is_stored_privately_and_frozen_onto_a_letter(): void
    {
        Storage::fake('local');

        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->paidEmployee();
        $company = $employee->company;

        $this->actingAs($admin->user)
            ->post(route('companies.signatories.store', $company), [
                'name' => 'Anita Rao',
                'designation' => 'Director',
                'status' => 'active',
                'signature' => UploadedFile::fake()->image('signature.png', 300, 100),
            ])->assertRedirect();

        $signatory = $company->fresh()->defaultSignatory();

        $this->assertNotNull($signatory->signature_path);
        Storage::disk('local')->assertExists($signatory->signature_path);
        $this->assertStringNotContainsString('public', $signatory->signature_path);

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->assertSame($signatory->signature_path, $letter->signature_path);
        $this->assertStringStartsWith('data:image/', $letter->signatureDataUri());
    }

    public function test_replacing_a_signature_leaves_an_issued_letter_alone(): void
    {
        Storage::fake('local');

        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->paidEmployee();
        $company = $employee->company;

        $this->actingAs($admin->user)->post(route('companies.signatories.store', $company), [
            'name' => 'Anita Rao',
            'status' => 'active',
            'signature' => UploadedFile::fake()->image('first.png', 300, 100),
        ]);

        $signatory = $company->fresh()->defaultSignatory();
        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);
        $original = $letter->signature_path;

        $this->actingAs($admin->user)->put(route('companies.signatories.update', [$company, $signatory]), [
            'name' => 'Anita Rao',
            'status' => 'active',
            'signature' => UploadedFile::fake()->image('second.png', 300, 100),
        ]);

        $this->assertNotSame($original, $signatory->fresh()->signature_path);
        Storage::disk('local')->assertExists($original);
        $this->assertNotNull($letter->fresh()->signatureDataUri());
    }

    public function test_the_signature_is_streamed_rather_than_linked(): void
    {
        Storage::fake('local');

        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE);
        $company = $this->defaultCompany();

        $this->actingAs($admin->user)->post(route('companies.signatories.store', $company), [
            'name' => 'Anita Rao',
            'status' => 'active',
            'signature' => UploadedFile::fake()->image('signature.png', 300, 100),
        ]);

        $signatory = $company->fresh()->defaultSignatory();
        $url = route('companies.signatories.signature', [$company, $signatory]);

        $this->actingAs($admin->user)->get($url)->assertOk();
        $this->actingAs($employee->user)->get($url)->assertForbidden();
    }
}
