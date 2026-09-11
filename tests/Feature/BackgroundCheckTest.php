<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Models\Employee;
use App\Services\BackgroundCheckService;
use App\Support\BackgroundCheckRequirements;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackgroundCheckTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------- inviting

    public function test_hr_can_ask_a_new_joiner_for_their_documents(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('background-checks.invite'), [
            'employee_id' => $joiner->id,
            'due_on' => now()->addDays(14)->toDateString(),
            'requirements' => [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::EDUCATION],
        ])->assertRedirect();

        $check = $joiner->fresh()->backgroundCheck;

        $this->assertNotNull($check);
        $this->assertSame(BackgroundCheck::INVITED, $check->status);
        $this->assertCount(2, $check->items);
        $this->assertSame($hr->user->id, $check->invited_by);

        Mail::assertQueued(
            TemplatedMail::class,
            fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::BGV_INVITED
                && $mail->hasTo($joiner->email),
        );
    }

    public function test_creating_an_employee_can_ask_for_verification_in_the_same_step(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('employees.store'), [
            'first_name' => 'Nadia', 'last_name' => 'Rahman',
            'email' => 'nadia.rahman@example.test',
            'company_id' => $this->defaultCompany()->id,
            'branch_id' => $hr->branch_id,
            'employment_type' => 'full_time', 'employment_status' => 'probation',
            'date_of_joining' => now()->toDateString(), 'notice_period_days' => 30,
            'status' => 'active', 'create_account' => '1', 'role' => Roles::EMPLOYEE,
            'send_welcome_email' => '1',
            'request_bgv' => '1',
            'bgv_requirements' => [BackgroundCheckRequirements::IDENTITY],
        ])->assertRedirect();

        $joiner = Employee::where('email', 'nadia.rahman@example.test')->firstOrFail();

        $this->assertNotNull($joiner->backgroundCheck);
        $this->assertCount(1, $joiner->backgroundCheck->items);

        // Both the welcome and the checklist go out; they say different things.
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->eventKey === NotificationEvents::EMPLOYEE_WELCOME);
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->eventKey === NotificationEvents::BGV_INVITED);
    }

    public function test_re_inviting_does_not_discard_what_was_already_uploaded(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $service = app(BackgroundCheckService::class);

        $check = $service->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);
        $item = $check->items->first();
        $service->recordUpload($item, UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf'), [
            'document_type' => 'Passport', 'document_number' => 'X1234567',
        ]);

        $service->invite($joiner->fresh(), [
            BackgroundCheckRequirements::IDENTITY,
            BackgroundCheckRequirements::EDUCATION,
        ], $hr->user);

        $check = $joiner->fresh()->backgroundCheck->load('items');

        $this->assertCount(2, $check->items);
        $this->assertTrue($check->items->firstWhere('requirement', BackgroundCheckRequirements::IDENTITY)->hasUpload());
    }

    // ------------------------------------------------------------- employee

    public function test_an_employee_uploads_each_document_and_submits(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $check = app(BackgroundCheckService::class)
            ->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);
        $item = $check->items->first();

        $this->actingAs($joiner->user)->get(route('my-verification.edit'))
            ->assertOk()
            ->assertSee('Identity document');

        // Nothing can be submitted before the documents are there.
        $this->actingAs($joiner->user)->post(route('my-verification.submit'))
            ->assertSessionHasErrors('submit');

        $this->actingAs($joiner->user)->post(route('my-verification.upload', $item), [
            'file' => UploadedFile::fake()->create('passport.pdf', 30, 'application/pdf'),
            'details' => ['document_type' => 'Passport', 'document_number' => 'X1234567'],
        ])->assertRedirect();

        $item = $item->fresh();
        $this->assertSame(BackgroundCheckItem::UPLOADED, $item->status);
        $this->assertSame('Passport', $item->detail('document_type'));
        Storage::assertExists($item->file_path);

        $this->actingAs($joiner->user)->post(route('my-verification.submit'))->assertRedirect();

        $this->assertSame(BackgroundCheck::SUBMITTED, $check->fresh()->status);

        Mail::assertQueued(
            TemplatedMail::class,
            fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::BGV_SUBMITTED
                && $mail->hasTo($hr->email),
        );
    }

    public function test_the_required_details_beside_a_document_are_enforced(): void
    {
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $check = app(BackgroundCheckService::class)
            ->invite($joiner, [BackgroundCheckRequirements::EDUCATION], $hr->user);

        $this->actingAs($joiner->user)->post(route('my-verification.upload', $check->items->first()), [
            'file' => UploadedFile::fake()->create('degree.pdf', 10, 'application/pdf'),
            'details' => ['qualification' => 'BSc'],
        ])->assertSessionHasErrors(['details.institution', 'details.completed_year']);
    }

    public function test_one_employee_cannot_touch_another_persons_verification(): void
    {
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $other = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $check = app(BackgroundCheckService::class)->invite($joiner, null, $hr->user);
        app(BackgroundCheckService::class)->invite($other, null, $hr->user);

        $this->actingAs($other->user)->post(route('my-verification.upload', $check->items->first()), [
            'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
            'details' => ['document_type' => 'Passport', 'document_number' => 'X1'],
        ])->assertForbidden();
    }

    public function test_a_submitted_case_is_locked_to_the_employee(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = $this->submittedCheck($hr, $joiner);

        $this->actingAs($joiner->user)
            ->post(route('my-verification.upload', $check->items->first()), [
                'file' => UploadedFile::fake()->create('another.pdf', 10, 'application/pdf'),
                'details' => ['document_type' => 'Passport', 'document_number' => 'X2'],
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------- reviewing

    public function test_hr_accepts_the_documents_and_the_employee_is_onboarded(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = $this->submittedCheck($hr, $joiner);

        $this->actingAs($hr->user)->get(route('background-checks.show', $check))
            ->assertOk()
            ->assertSee('Identity document');

        $this->actingAs($hr->user)
            ->post(route('background-checks.items.review', $check->items->first()), ['decision' => 'verify'])
            ->assertRedirect();

        $this->assertTrue($check->items->first()->fresh()->isVerified());

        $this->actingAs($hr->user)
            ->post(route('background-checks.complete', $check), ['review_remarks' => 'All in order.'])
            ->assertRedirect();

        $check = $check->fresh();
        $this->assertSame(BackgroundCheck::VERIFIED, $check->status);
        $this->assertNotNull($check->onboarded_at);
        $this->assertSame($hr->user->id, $check->reviewed_by);

        Mail::assertQueued(
            TemplatedMail::class,
            fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::BGV_VERIFIED
                && $mail->hasTo($joiner->email),
        );
    }

    public function test_a_document_can_be_sent_back_with_a_reason(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = $this->submittedCheck($hr, $joiner);

        // A rejection has to say why.
        $this->actingAs($hr->user)
            ->post(route('background-checks.items.review', $check->items->first()), ['decision' => 'reject'])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($hr->user)->post(route('background-checks.items.review', $check->items->first()), [
            'decision' => 'reject',
            'remarks' => 'The number is not readable. Please send a clearer scan.',
        ])->assertRedirect();

        $this->actingAs($hr->user)->post(route('background-checks.request-changes', $check), [
            'review_remarks' => 'One document to redo.',
        ])->assertRedirect();

        $check = $check->fresh('items');
        $this->assertSame(BackgroundCheck::CHANGES_REQUESTED, $check->status);
        $this->assertTrue($check->isOpenToEmployee());
        $this->assertCount(1, $check->itemsNeedingWork());

        Mail::assertQueued(
            TemplatedMail::class,
            fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::BGV_CHANGES_REQUESTED
                && $mail->hasTo($joiner->email),
        );

        // The employee can replace it, and that clears the rejection.
        $this->actingAs($joiner->user)->post(route('my-verification.upload', $check->items->first()), [
            'file' => UploadedFile::fake()->create('clearer.pdf', 40, 'application/pdf'),
            'details' => ['document_type' => 'Passport', 'document_number' => 'X1234567'],
        ])->assertRedirect();

        $item = $check->items->first()->fresh();
        $this->assertSame(BackgroundCheckItem::UPLOADED, $item->status);
        $this->assertNull($item->remarks);
    }

    public function test_onboarding_is_refused_while_a_document_is_missing(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $check = app(BackgroundCheckService::class)->invite(
            $joiner,
            [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::EDUCATION],
            $hr->user,
        );

        $this->actingAs($hr->user)->post(route('background-checks.complete', $check))->assertRedirect();

        $this->assertSame(BackgroundCheck::INVITED, $check->fresh()->status);
    }

    // ------------------------------------------------------------ the files

    public function test_a_document_is_only_readable_by_its_owner_and_hr(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $nosy = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = $this->submittedCheck($hr, $joiner);
        $item = $check->items->first();

        $this->actingAs($joiner->user)->get(route('background-checks.items.document', $item))->assertOk();
        $this->actingAs($hr->user)->get(route('background-checks.items.document', $item))->assertOk();
        $this->actingAs($nosy->user)->get(route('background-checks.items.document', $item))->assertForbidden();
    }

    public function test_an_employee_cannot_reach_the_review_screens(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = $this->submittedCheck($hr, $joiner);

        $this->actingAs($joiner->user)->get(route('background-checks.index'))->assertForbidden();
        $this->actingAs($joiner->user)->get(route('background-checks.show', $check))->assertForbidden();
        $this->actingAs($joiner->user)
            ->post(route('background-checks.complete', $check))
            ->assertForbidden();
    }

    public function test_someone_never_asked_is_told_so_plainly(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->get(route('my-verification.edit'))->assertNotFound();
    }

    /** A case with one identity document uploaded and submitted for review. */
    protected function submittedCheck(Employee $hr, Employee $joiner): BackgroundCheck
    {
        $service = app(BackgroundCheckService::class);

        $check = $service->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        $service->recordUpload(
            $check->items->first(),
            UploadedFile::fake()->create('passport.pdf', 25, 'application/pdf'),
            ['document_type' => 'Passport', 'document_number' => 'X1234567'],
        );

        return $service->submit($check->fresh('items'));
    }
    // ------------------------------------------------- changing the list

    public function test_hr_can_change_what_was_asked_for_after_the_invitation(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $fresher = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = app(BackgroundCheckService::class)->invite(
            $fresher,
            [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::EXPERIENCE],
            $hr->user,
        );

        // The experience letter was ticked by mistake: this joiner has never worked.
        $this->actingAs($hr->user)
            ->put(route('background-checks.requirements', $check), [
                'requirements' => [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::PHOTOGRAPH],
                'due_on' => '2026-10-01',
            ])
            ->assertRedirect(route('background-checks.show', $check))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Added Photograph') && str_contains($m, 'removed Experience letter'));

        $check = $check->fresh();
        $keys = $check->items->pluck('requirement')->all();
        $this->assertEqualsCanonicalizing([BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::PHOTOGRAPH], $keys);
        $this->assertSame('2026-10-01', $check->due_on->toDateString());

        // The revised checklist goes to the employee: one email for the
        // invitation and one for the change.
        $invitations = Mail::queued(TemplatedMail::class)
            ->filter(fn (TemplatedMail $mail) => $mail->eventKey === NotificationEvents::BGV_INVITED && $mail->hasTo($fresher->email));
        $this->assertCount(2, $invitations);
    }

    public function test_dropping_a_document_the_employee_had_uploaded_removes_the_file(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $service = app(BackgroundCheckService::class);
        $check = $service->invite(
            $joiner,
            [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::EXPERIENCE],
            $hr->user,
        );

        $letter = $check->items->firstWhere('requirement', BackgroundCheckRequirements::EXPERIENCE);
        $letter = $service->recordUpload($letter, UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'), [
            'employer' => 'Acme', 'job_title' => 'Clerk', 'from' => '2024-01-01', 'to' => '2025-01-01',
        ]);
        Storage::assertExists($letter->file_path);

        $this->actingAs($hr->user)
            ->put(route('background-checks.requirements', $check), [
                'requirements' => [BackgroundCheckRequirements::IDENTITY],
            ])
            ->assertRedirect();

        Storage::assertMissing($letter->file_path);
        $this->assertDatabaseMissing('background_check_items', ['id' => $letter->id]);
        // Only a removal: nothing new to tell the employee about.
        Mail::assertQueued(TemplatedMail::class, 1);
    }

    public function test_adding_a_document_after_submission_hands_the_case_back_to_the_employee(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $service = app(BackgroundCheckService::class);
        $check = $service->invite($joiner, [BackgroundCheckRequirements::PHOTOGRAPH], $hr->user);

        $service->recordUpload($check->items->first(), UploadedFile::fake()->image('me.jpg'));
        $service->submit($check->fresh());
        $this->assertSame(BackgroundCheck::SUBMITTED, $check->fresh()->status);

        $this->actingAs($hr->user)
            ->put(route('background-checks.requirements', $check), [
                'requirements' => [BackgroundCheckRequirements::PHOTOGRAPH, BackgroundCheckRequirements::IDENTITY],
            ])
            ->assertRedirect();

        $check = $check->fresh();
        $this->assertSame(BackgroundCheck::IN_PROGRESS, $check->status);
        $this->assertTrue($check->isOpenToEmployee());
        // What was already provided is untouched.
        $this->assertTrue($check->items->firstWhere('requirement', BackgroundCheckRequirements::PHOTOGRAPH)->hasUpload());
    }

    public function test_the_list_cannot_change_once_the_case_is_verified(): void
    {
        Mail::fake();
        Storage::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $service = app(BackgroundCheckService::class);
        $check = $service->invite($joiner, [BackgroundCheckRequirements::PHOTOGRAPH], $hr->user);

        $service->recordUpload($check->items->first(), UploadedFile::fake()->image('me.jpg'));
        $service->submit($check->fresh());
        $service->complete($check->fresh(), $hr->user);

        $this->actingAs($hr->user)
            ->from(route('background-checks.show', $check))
            ->put(route('background-checks.requirements', $check), [
                'requirements' => [BackgroundCheckRequirements::IDENTITY],
            ])
            ->assertRedirect(route('background-checks.show', $check))
            ->assertSessionHas('error');

        $this->assertSame([BackgroundCheckRequirements::PHOTOGRAPH], $check->fresh()->items->pluck('requirement')->all());
    }

    public function test_the_list_must_keep_at_least_one_document(): void
    {
        Mail::fake();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $check = app(BackgroundCheckService::class)->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        $this->actingAs($hr->user)
            ->put(route('background-checks.requirements', $check), ['requirements' => []])
            ->assertSessionHasErrors('requirements');

        $this->assertCount(1, $check->fresh()->items);
    }
}
