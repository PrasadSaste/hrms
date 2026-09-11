<?php

namespace Tests\Feature;

use App\Models\BackgroundCheck;
use App\Models\Employee;
use App\Models\HelpArticle;
use App\Services\BackgroundCheckService;
use App\Services\LetterService;
use App\Support\BackgroundCheckRequirements;
use App\Support\LetterTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The three things the API used to drop an employee at.
 *
 * Attendance, leave and payslips were reachable from a phone; a letter one had
 * been issued, the documents a joiner owes and the guide explaining a screen
 * were not. Each is the same service the web screen calls, so a client cannot
 * reach a record it could not have opened in a browser.
 */
class SelfServiceApiTest extends TestCase
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
        return $this->postJson('/api/v1/login', [
            'email' => $employee->email,
            'password' => 'Password123!',
            'device_name' => 'test-suite',
        ])->json('token');
    }

    /** @return array<string, string> */
    protected function auth(Employee $employee): array
    {
        return ['Authorization' => 'Bearer '.$this->tokenFor($employee)];
    }

    /*
    |--------------------------------------------------------------------------
    | Letters
    |--------------------------------------------------------------------------
    */

    public function test_an_employee_lists_and_reads_their_own_letters(): void
    {
        $employee = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $letter = app(LetterService::class)
            ->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/letters')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference', $letter->reference)
            ->assertJsonPath('data.0.type', LetterTypes::APPOINTMENT)
            ->assertJsonMissingPath('data.0.body', 'A list of letters is not a list of full letters.');

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/letters/'.$letter->id)
            ->assertOk()
            ->assertJsonPath('data.signatory_name', $letter->signatory_name)
            ->assertJsonPath('data.body', $letter->body);
    }

    public function test_the_words_returned_are_the_frozen_ones(): void
    {
        $employee = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $letter = app(LetterService::class)
            ->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);

        // The template changes after the fact; the issued letter must not.
        $letter->update(['body' => 'The words as they were signed.']);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/letters/'.$letter->id)
            ->assertOk()
            ->assertJsonPath('data.body', 'The words as they were signed.');
    }

    public function test_a_letter_downloads_as_a_pdf(): void
    {
        $employee = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $letter = app(LetterService::class)
            ->issue($employee, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);

        $response = $this->withHeaders($this->auth($employee))
            ->get('/api/v1/letters/'.$letter->id.'/download');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_one_employee_cannot_read_another_employees_letter(): void
    {
        $employee = $this->makeEmployee();
        $colleague = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $letter = app(LetterService::class)
            ->issue($colleague, LetterTypes::APPOINTMENT, ['probation_months' => 6], $hr->user);

        $this->withHeaders($this->auth($employee))
            ->getJson('/api/v1/letters/'.$letter->id)
            ->assertForbidden();

        $this->withHeaders($this->auth($employee))
            ->get('/api/v1/letters/'.$letter->id.'/download')
            ->assertForbidden();

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/letters')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Background verification
    |--------------------------------------------------------------------------
    */

    /** @return array{0: Employee, 1: BackgroundCheck} */
    protected function joinerWithCheck(): array
    {
        $joiner = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $check = app(BackgroundCheckService::class)
            ->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        return [$joiner, $check];
    }

    public function test_a_joiner_sees_the_checklist_and_what_each_item_asks_for(): void
    {
        [$joiner] = $this->joinerWithCheck();

        $response = $this->withHeaders($this->auth($joiner))
            ->getJson('/api/v1/background-check')
            ->assertOk()
            ->assertJsonPath('data.open_to_employee', true)
            ->assertJsonPath('data.ready_to_submit', false)
            ->assertJsonPath('data.items.0.requirement', BackgroundCheckRequirements::IDENTITY)
            ->assertJsonPath('data.items.0.has_upload', false);

        // The fields to type in travel with the item, so a client draws the
        // form without keeping its own copy of the catalogue.
        $this->assertNotEmpty($response->json('data.items.0.fields'));
        $this->assertArrayHasKey('label', $response->json('data.items.0.fields.0'));
    }

    public function test_a_joiner_uploads_a_document_and_hands_the_set_over(): void
    {
        [$joiner, $check] = $this->joinerWithCheck();
        $item = $check->items->first();

        $this->withHeaders($this->auth($joiner))->post('/api/v1/background-check/items/'.$item->id, [
            'file' => UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf'),
            'details' => ['document_type' => 'Passport', 'document_number' => 'X1234567'],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.has_upload', true)
            ->assertJsonPath('data.ready_to_submit', true);

        $this->withHeaders($this->auth($joiner))->postJson('/api/v1/background-check/submit')
            ->assertOk()
            ->assertJsonPath('data.awaiting_review', true)
            ->assertJsonPath('data.open_to_employee', false);

        // And the document reads back, from the private disk.
        $this->withHeaders($this->auth($joiner))
            ->get('/api/v1/background-check/items/'.$item->id.'/document')
            ->assertOk();
    }

    public function test_a_set_with_a_document_missing_cannot_be_submitted(): void
    {
        [$joiner] = $this->joinerWithCheck();

        $this->withHeaders($this->auth($joiner))->postJson('/api/v1/background-check/submit')
            ->assertStatus(422)
            ->assertJsonValidationErrors('submit');
    }

    public function test_a_joiner_cannot_touch_somebody_elses_documents(): void
    {
        [, $check] = $this->joinerWithCheck();
        $stranger = $this->makeEmployee();

        app(BackgroundCheckService::class)->invite(
            $stranger,
            [BackgroundCheckRequirements::IDENTITY],
            $this->makeEmployee(Roles::HR_MANAGER)->user,
        );

        $notTheirs = $check->items->first();

        $this->withHeaders($this->auth($stranger))
            ->post('/api/v1/background-check/items/'.$notTheirs->id, [
                'file' => UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf'),
                'details' => ['document_type' => 'Passport', 'document_number' => 'X1234567'],
            ])
            ->assertForbidden();

        $this->withHeaders($this->auth($stranger))
            ->get('/api/v1/background-check/items/'.$notTheirs->id.'/document')
            ->assertForbidden();
    }

    public function test_verification_is_reachable_while_verification_is_outstanding(): void
    {
        [$joiner] = $this->joinerWithCheck();

        // The point of the exception: everything behind bgv.cleared is shut to
        // this person, and the thing that clears it must not be.
        $this->withHeaders($this->auth($joiner))->getJson('/api/v1/payslips')->assertForbidden();
        $this->withHeaders($this->auth($joiner))->getJson('/api/v1/letters')->assertForbidden();
        $this->withHeaders($this->auth($joiner))->getJson('/api/v1/background-check')->assertOk();
    }

    public function test_somebody_never_asked_to_verify_is_told_so_plainly(): void
    {
        $employee = $this->makeEmployee();

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/background-check')
            ->assertNotFound()
            ->assertJsonPath('message', 'You have not been asked for background verification. There is nothing to do here.');
    }

    /*
    |--------------------------------------------------------------------------
    | Help guides
    |--------------------------------------------------------------------------
    */

    public function test_the_guides_come_back_grouped_and_searchable(): void
    {
        $employee = $this->makeEmployee();

        HelpArticle::create([
            'slug' => 'applying-for-leave', 'group' => 'My workspace', 'title' => 'Applying for leave',
            'summary' => 'How to ask for a day off', 'body' => 'Open Leave and press Apply.',
            'is_published' => true, 'position' => 1,
        ]);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help')
            ->assertOk()
            ->assertJsonPath('data.My workspace.0.title', 'Applying for leave')
            ->assertJsonMissingPath('data.My workspace.0.body', 'A list of guides is not a list of full guides.');

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help?q=press+apply')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help?q=nothing+says+this')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_a_single_guide_carries_both_markdown_and_html(): void
    {
        $employee = $this->makeEmployee();

        HelpArticle::create([
            'slug' => 'punching-in', 'group' => 'My workspace', 'title' => 'Punching in',
            'body' => 'Press **Check in**.', 'is_published' => true, 'position' => 1,
        ]);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help/punching-in')
            ->assertOk()
            ->assertJsonPath('data.body', 'Press **Check in**.')
            ->assertJsonFragment(['body_html' => "<p>Press <strong>Check in</strong>.</p>\n"]);
    }

    public function test_a_guide_to_a_screen_you_cannot_open_is_refused(): void
    {
        $employee = $this->makeEmployee();

        HelpArticle::create([
            'slug' => 'running-payroll', 'group' => 'Payroll', 'title' => 'Running payroll',
            'body' => 'Create a run.', 'permission' => 'payroll.process',
            'is_published' => true, 'position' => 1,
        ]);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help/running-payroll')
            ->assertForbidden();

        // And it is not even listed, so nobody learns the screen exists.
        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_an_unpublished_draft_is_invisible(): void
    {
        $employee = $this->makeEmployee();

        HelpArticle::create([
            'slug' => 'half-written', 'group' => 'My workspace', 'title' => 'Half written',
            'body' => 'Not ready.', 'is_published' => false, 'position' => 1,
        ]);

        $this->withHeaders($this->auth($employee))->getJson('/api/v1/help/half-written')->assertNotFound();
    }

    public function test_none_of_it_answers_without_a_token(): void
    {
        foreach (['/api/v1/letters', '/api/v1/background-check', '/api/v1/help'] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }
}
