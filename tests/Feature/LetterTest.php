<?php

namespace Tests\Feature;

use App\Mail\TemplatedLetterMail;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SalaryStructure;
use App\Services\LetterPdfService;
use App\Services\LetterService;
use App\Support\LetterTypes;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The letters a company issues: offer, appointment, confirmation, increment,
 * experience and the rest.
 */
class LetterTest extends TestCase
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

    /** Somebody on ₹9,00,000 a year. */
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

        return $employee->fresh(['company', 'branch', 'department', 'designation']);
    }

    // ------------------------------------------------------- the catalogue

    public function test_every_letter_in_the_catalogue_can_be_written(): void
    {
        $employee = $this->paidEmployee(['date_of_exit' => '2026-08-31']);

        foreach (LetterTypes::keys() as $type) {
            $preview = $this->letters->preview($employee, $type, $this->sampleFields($type));

            $this->assertNotEmpty($preview['subject'], $type.' has no heading.');
            $this->assertNotEmpty($preview['body'], $type.' has no body.');
            $this->assertStringNotContainsString('{{', $preview['body'], $type.' left a placeholder unfilled.');
        }
    }

    /** Enough to satisfy every type's required fields at once. */
    protected function sampleFields(string $type): array
    {
        return [
            'offered_ctc' => 900000,
            'start_date' => '2026-10-01',
            'revised_ctc' => 990000,
            'effective_from' => '2026-10-01',
            'probation_months' => 6,
            'purpose' => 'a home loan application',
            'subject_matter' => 'repeated late arrival',
            'incident_details' => 'You arrived after 10:30 on four days in August.',
            'expected_action' => 'Please arrive by the shift start time.',
        ];
    }

    // --------------------------------------------------------- the values

    public function test_the_values_come_from_the_employee_and_their_pay(): void
    {
        $employee = $this->paidEmployee();

        $preview = $this->letters->preview($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6]);

        $this->assertStringContainsString($employee->employee_code, $preview['body']);
        $this->assertStringContainsString('₹9,00,000.00', $preview['body']);
        $this->assertStringContainsString('₹30,000.00', $preview['body']);
        $this->assertStringContainsString('01 Apr 2023', $preview['body']);
    }

    public function test_an_increment_works_out_its_own_rise(): void
    {
        $employee = $this->paidEmployee();

        $preview = $this->letters->preview($employee, LetterTypes::INCREMENT, [
            'revised_ctc' => 990000,
            'effective_from' => '2026-10-01',
        ]);

        // 9,00,000 to 9,90,000 is 90,000, which is ten per cent.
        $this->assertStringContainsString('₹9,00,000.00', $preview['body']);
        $this->assertStringContainsString('₹9,90,000.00', $preview['body']);
        $this->assertStringContainsString('₹90,000.00', $preview['body']);
        $this->assertStringContainsString('10.0%', $preview['body']);
    }

    public function test_a_number_that_is_not_money_does_not_grow_a_currency_symbol(): void
    {
        $employee = $this->paidEmployee();

        $preview = $this->letters->preview($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6]);

        $this->assertStringContainsString('**6 months**', $preview['body']);
        $this->assertStringNotContainsString('₹6', $preview['body']);
    }

    public function test_an_experience_letter_states_the_period_served(): void
    {
        $employee = $this->paidEmployee(['date_of_exit' => '2026-08-31']);

        $preview = $this->letters->preview($employee, LetterTypes::EXPERIENCE);

        $this->assertStringContainsString('01 Apr 2023', $preview['body']);
        $this->assertStringContainsString('31 Aug 2026', $preview['body']);
        $this->assertStringContainsString('3 years and 4 months', $preview['body']);
    }

    public function test_a_leaving_letter_needs_a_last_working_day(): void
    {
        $employee = $this->paidEmployee();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('needs a last working day');

        $this->letters->issue($employee, LetterTypes::EXPERIENCE, []);
    }

    public function test_a_letter_addressed_to_nobody_says_so(): void
    {
        $employee = $this->paidEmployee();

        $open = $this->letters->preview($employee, LetterTypes::SALARY_CERTIFICATE, []);
        $addressed = $this->letters->preview($employee, LetterTypes::SALARY_CERTIFICATE, [
            'addressed_to' => 'The Manager, State Bank',
        ]);

        $this->assertStringContainsString('To whomsoever it may concern', $open['body']);
        $this->assertStringContainsString('The Manager, State Bank', $addressed['body']);
    }

    public function test_a_relieving_letter_changes_its_words_with_the_tick_box(): void
    {
        $employee = $this->paidEmployee(['date_of_exit' => '2026-08-31']);

        $settled = $this->letters->preview($employee, LetterTypes::RELIEVING, ['dues_settled' => true]);
        $pending = $this->letters->preview($employee, LetterTypes::RELIEVING, ['dues_settled' => false]);

        $this->assertStringContainsString('has been completed', $settled['body']);
        $this->assertStringContainsString('is in progress', $pending['body']);
    }

    // -------------------------------------------------------- issuing them

    public function test_issuing_numbers_the_letter(): void
    {
        $employee = $this->paidEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $first = $this->letters->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);
        $second = $this->letters->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);

        $this->assertStringEndsWith('/APT/2026/0001', $first->reference);
        $this->assertStringEndsWith('/APT/2026/0002', $second->reference);
        $this->assertSame($hr->user->id, $first->issued_by);
    }

    public function test_the_words_are_frozen_when_it_is_issued(): void
    {
        $employee = $this->paidEmployee();

        $letter = $this->letters->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6]);
        $original = $letter->body;

        // Somebody rewrites the template two years later.
        LetterTemplate::create([
            'type' => LetterTypes::APPOINTMENT,
            'body' => 'Entirely different wording.',
        ]);

        $this->assertSame($original, $letter->fresh()->body,
            'A letter somebody holds a signed copy of must not be rewritten.');
        $this->assertStringContainsString(
            'Entirely different wording',
            $this->letters->preview($employee, LetterTypes::APPOINTMENT)['body'],
            'The next letter should use the new wording.',
        );
    }

    public function test_a_letter_carries_the_company_that_employed_them(): void
    {
        $employee = $this->paidEmployee();

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->assertSame($employee->company_id, $letter->company_id);
    }

    public function test_the_pdf_renders(): void
    {
        $employee = $this->paidEmployee();

        $letter = $this->letters->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6]);
        $bytes = app(LetterPdfService::class)->bytes($letter);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    // ------------------------------------------------------- the screens

    public function test_hr_can_issue_a_letter_from_the_screen(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee(['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('letters.store'), [
            'employee_id' => $employee->id,
            'type' => LetterTypes::CONFIRMATION,
            'fields' => ['effective_from' => '2026-10-01'],
            'send_email' => 1,
        ])->assertRedirect();

        $letter = Letter::first();

        $this->assertNotNull($letter);
        $this->assertSame(LetterTypes::CONFIRMATION, $letter->type);
        $this->assertNotNull($letter->emailed_at, 'Ticking the box should have sent it.');

        Mail::assertQueued(TemplatedLetterMail::class, fn (TemplatedLetterMail $mail) => $mail->hasTo($employee->email)
            && $mail->eventKey === NotificationEvents::LETTER_ISSUED);
    }

    public function test_issuing_without_the_box_ticked_sends_nothing(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee(['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('letters.store'), [
            'employee_id' => $employee->id,
            'type' => LetterTypes::EMPLOYMENT_PROOF,
        ])->assertRedirect();

        Mail::assertNothingQueued();
        $this->assertNull(Letter::first()->emailed_at);
    }

    public function test_an_employee_sees_their_own_letters_and_not_anybody_elses(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee(['branch' => $hr->branch]);
        $colleague = $this->paidEmployee(['branch' => $hr->branch]);

        $mine = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);
        $theirs = $this->letters->issue($colleague, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->actingAs($employee->user)
            ->get(route('my-letters.index'))
            ->assertOk()
            ->assertSee($mine->reference)
            ->assertDontSee($theirs->reference);

        $this->actingAs($employee->user)->get(route('my-letters.download', $mine))->assertOk();
        $this->actingAs($employee->user)->get(route('my-letters.download', $theirs))->assertForbidden();
    }

    public function test_an_employee_cannot_reach_the_register_or_issue_anything(): void
    {
        $employee = $this->paidEmployee();

        $this->actingAs($employee->user)->get(route('letters.index'))->assertForbidden();
        $this->actingAs($employee->user)->get(route('letters.create'))->assertForbidden();
        $this->actingAs($employee->user)->post(route('letters.store'), [
            'employee_id' => $employee->id,
            'type' => LetterTypes::EXPERIENCE,
        ])->assertForbidden();
    }

    public function test_a_branch_manager_does_not_see_another_branch(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $elsewhere = $this->paidEmployee();

        $letter = $this->letters->issue($elsewhere, LetterTypes::EMPLOYMENT_PROOF, []);

        $this->actingAs($manager->user)->get(route('letters.show', $letter))->assertForbidden();
    }

    public function test_hr_can_download_the_pdf(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->paidEmployee(['branch' => $hr->branch]);

        $letter = $this->letters->issue($employee, LetterTypes::EMPLOYMENT_PROOF, []);

        $response = $this->actingAs($hr->user)->get(route('letters.download', $letter))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    // ------------------------------------------------------- the wording

    public function test_the_wording_can_be_changed_and_put_back(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->put(route('letter-templates.update', LetterTypes::OFFER), [
            'subject' => 'Our offer to you',
            'body' => 'Dear {{ first_name }}, we would like you to join us.',
        ])->assertRedirect();

        $this->assertTrue(LetterTemplate::isCustomised(LetterTypes::OFFER));
        $this->assertSame('Our offer to you', LetterTemplate::resolve(LetterTypes::OFFER)['subject']);

        $this->actingAs($admin->user)
            ->delete(route('letter-templates.reset', LetterTypes::OFFER))
            ->assertRedirect();

        $this->assertFalse(LetterTemplate::isCustomised(LetterTypes::OFFER));
        $this->assertSame(
            LetterTypes::find(LetterTypes::OFFER)['subject'],
            LetterTemplate::resolve(LetterTypes::OFFER)['subject'],
        );
    }

    public function test_wording_identical_to_the_default_is_not_stored_as_a_change(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $defaults = LetterTypes::find(LetterTypes::NOC);

        $this->actingAs($admin->user)->put(route('letter-templates.update', LetterTypes::NOC), [
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
        ])->assertRedirect();

        $this->assertFalse(LetterTemplate::isCustomised(LetterTypes::NOC));
    }

    public function test_the_wording_screen_explains_placeholders_without_compiling_them(): void
    {
        $admin = $this->makeEmployee(Roles::HR_MANAGER);

        $response = $this->actingAs($admin->user)
            ->get(route('letter-templates.edit', LetterTypes::OFFER))
            ->assertOk();

        // Blade compiles the whole template as text, component attribute
        // values included, so an example pair of braces written plainly is
        // compiled and the PHP is printed at the reader instead of the token.
        $response->assertSee('{{ token }}', escape: false);
        $this->assertStringNotContainsString('echo e(token)', $response->getContent());
    }

    public function test_the_wording_screen_is_reachable_from_the_navigation(): void
    {
        $admin = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($admin->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('letter-templates.index'));
    }

    public function test_the_wording_console_previews_with_sample_values(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->postJson(route('letter-templates.preview', LetterTypes::EXPERIENCE), [
                'body' => 'This certifies **{{ employee_name }}** worked here.',
            ])
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Experience certificate'])
            ->assertSee('Priya Sharma', escape: false);
    }

    public function test_a_template_is_never_evaluated_only_substituted(): void
    {
        $employee = $this->paidEmployee();

        LetterTemplate::create([
            'type' => LetterTypes::NOC,
            'body' => 'Hello {{ employee_name }} <script>alert(1)</script> {{ php_self }}',
        ]);

        $letter = $this->letters->issue($employee, LetterTypes::NOC, ['purpose' => 'travel']);

        $this->assertStringContainsString($employee->full_name, $letter->body);
        // The unknown token becomes a dash, and the tag stays text once rendered.
        $this->assertStringContainsString('—', $letter->body);
        $this->assertStringNotContainsString('<script>', $letter->html());
    }

    public function test_only_somebody_with_the_permission_may_reword_a_letter(): void
    {
        $employee = $this->paidEmployee();

        $this->actingAs($employee->user)
            ->get(route('letter-templates.index'))
            ->assertForbidden();
    }
}
