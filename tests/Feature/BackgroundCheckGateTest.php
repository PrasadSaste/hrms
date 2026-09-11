<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBackgroundCheckCleared;
use App\Models\BackgroundCheck;
use App\Services\BackgroundCheckService;
use App\Support\BackgroundCheckRequirements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance, leave and salary stay closed to a new joiner until their
 * background verification has cleared.
 */
class BackgroundCheckGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake();
    }

    public function test_the_self_service_screens_are_closed_while_verification_is_open(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        app(BackgroundCheckService::class)->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        foreach (['attendance.index', 'leave.index', 'leave.balance', 'leave.create', 'payslips.index'] as $route) {
            $this->actingAs($joiner->user)->get(route($route))
                ->assertRedirect(route('my-verification.edit'))
                ->assertSessionHas('warning', EnsureBackgroundCheckCleared::MESSAGE);
        }

        $this->actingAs($joiner->user)->post(route('attendance.check-in'), ['latitude' => 12.9, 'longitude' => 77.6])
            ->assertRedirect(route('my-verification.edit'));
    }

    public function test_the_api_answers_with_a_403_while_verification_is_open(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        app(BackgroundCheckService::class)->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        Sanctum::actingAs($joiner->user);

        foreach (['api.attendance.today', 'api.leave.balance', 'api.payslips.index'] as $route) {
            $this->getJson(route($route))
                ->assertForbidden()
                ->assertJsonPath('background_check_status', BackgroundCheck::INVITED);
        }

        // The rest of the API is unaffected.
        $this->getJson(route('api.me'))->assertOk();
    }

    public function test_the_screens_open_once_hr_has_verified_the_documents(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $service = app(BackgroundCheckService::class);
        $check = $service->invite($joiner, [BackgroundCheckRequirements::PHOTOGRAPH], $hr->user);

        $service->recordUpload($check->items->first(), UploadedFile::fake()->image('me.jpg'));
        $service->submit($check->fresh());

        // Still closed while HR is looking at it.
        $this->actingAs($joiner->user)->get(route('attendance.index'))
            ->assertRedirect(route('my-verification.edit'));

        $service->complete($check->fresh(), $hr->user);

        // A fresh user instance, as every real request has: the one above
        // still carries the relations it loaded while the case was open.
        $user = $joiner->user->fresh();

        $this->actingAs($user)->get(route('attendance.index'))->assertOk();
        $this->actingAs($user)->get(route('leave.index'))->assertOk();
        $this->actingAs($user)->get(route('payslips.index'))->assertOk();
    }

    public function test_someone_who_was_never_asked_for_verification_is_not_affected(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get(route('attendance.index'))->assertOk();
        $this->actingAs($employee->user)->get(route('leave.index'))->assertOk();
        $this->actingAs($employee->user)->get(route('payslips.index'))->assertOk();
    }

    public function test_a_manager_can_still_decide_leave_while_their_own_verification_is_open(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        app(BackgroundCheckService::class)->invite($hr, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        $this->actingAs($hr->user)->get(route('leave.calendar'))->assertOk();
        $this->actingAs($hr->user)->get(route('background-checks.index'))->assertOk();
    }

    public function test_the_menu_shows_the_closed_screens_as_locked(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        app(BackgroundCheckService::class)->invite($joiner, [BackgroundCheckRequirements::IDENTITY], $hr->user);

        $this->actingAs($joiner->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Finish your background verification')
            ->assertSee('My Attendance, locked until your background verification is complete')
            ->assertDontSee('Today at work')
            ->assertDontSee('Check in');
    }

    public function test_the_verification_screen_walks_through_the_documents_step_by_step(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $joiner = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        app(BackgroundCheckService::class)->invite(
            $joiner,
            [BackgroundCheckRequirements::IDENTITY, BackgroundCheckRequirements::ADDRESS],
            $hr->user,
        );

        $this->actingAs($joiner->user)->get(route('my-verification.edit'))
            ->assertOk()
            ->assertSeeInOrder(['Step 1 of 2', 'Identity document', 'Step 2 of 2', 'Proof of address', 'Submit to HR'])
            ->assertSee('Opens when this is complete');
    }
}
