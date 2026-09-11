<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Services\AssetService;
use App\Support\AssetTypes;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Handing company property out and getting it back.
 *
 * The register earns its keep at two moments: when two people both think they
 * have the laptop, and when somebody walks out still holding it.
 */
class AssetTest extends TestCase
{
    use RefreshDatabase;

    protected AssetService $assets;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        $this->assets = app(AssetService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function asset(array $attributes = []): Asset
    {
        static $n = 0;
        $n++;

        return Asset::create(array_merge([
            'asset_tag' => 'BS-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'type' => AssetTypes::LAPTOP,
            'name' => 'ThinkPad X1',
            'serial_number' => 'SN'.$n,
            'condition' => 'good',
            'status' => Asset::IN_STOCK,
        ], $attributes));
    }

    /*
    |--------------------------------------------------------------------------
    | Issuing and returning
    |--------------------------------------------------------------------------
    */

    public function test_issuing_records_who_has_it_and_marks_it_out(): void
    {
        $employee = $this->makeEmployee();
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $asset = $this->asset();

        $assignment = $this->assets->issue($asset, $employee, $hr->user, [
            'condition_out' => 'new',
            'issue_remarks' => 'Charger and sleeve included.',
        ]);

        $this->assertSame(Asset::ISSUED, $asset->fresh()->status);
        $this->assertTrue($asset->fresh()->currentAssignment->employee->is($employee));
        $this->assertSame('new', $assignment->condition_out);
        $this->assertSame($hr->user->id, $assignment->issued_by);
        $this->assertNull($assignment->returned_on);
    }

    public function test_an_asset_can_only_be_with_one_person_at_a_time(): void
    {
        $first = $this->makeEmployee();
        $second = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $first);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is already with');

        $this->assets->issue($asset->fresh(), $second);
    }

    public function test_something_in_repair_cannot_be_issued(): void
    {
        $employee = $this->makeEmployee();
        $asset = $this->asset(['status' => Asset::IN_REPAIR]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be issued');

        $this->assets->issue($asset, $employee);
    }

    public function test_returning_closes_the_assignment_and_records_the_condition(): void
    {
        $employee = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $employee, null, ['condition_out' => 'new']);
        $assignment = $this->assets->return($asset->fresh(), null, [
            'condition_in' => 'fair',
            'return_remarks' => 'Dent on the lid.',
        ]);

        $asset = $asset->fresh();

        $this->assertSame(Asset::IN_STOCK, $asset->status);
        $this->assertSame('fair', $asset->condition, 'The next person sees the state it is actually in.');
        $this->assertSame('fair', $assignment->condition_in);
        $this->assertNotNull($assignment->returned_on);
        $this->assertTrue($assignment->deteriorated(), 'New out, fair back, is worse.');
    }

    public function test_something_that_comes_back_broken_does_not_go_back_on_the_shelf(): void
    {
        $employee = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $employee);
        $this->assets->return($asset->fresh(), null, [
            'condition_in' => 'damaged',
            'status' => Asset::IN_REPAIR,
        ]);

        $this->assertSame(Asset::IN_REPAIR, $asset->fresh()->status);
        $this->assertFalse($asset->fresh()->isAvailable());
    }

    public function test_taking_back_something_nobody_has_is_refused(): void
    {
        $asset = $this->asset();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not with anybody');

        $this->assets->return($asset);
    }

    public function test_a_handover_keeps_both_periods(): void
    {
        $first = $this->makeEmployee();
        $second = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $first, null, ['issued_on' => '2026-01-05']);
        $this->assets->transfer($asset->fresh(), $second, null, ['issued_on' => '2026-06-10']);

        // reorder, not orderBy: the relation is newest-first by default and an
        // appended order would never be reached.
        $assignments = $asset->fresh()->assignments()->reorder('id')->get();

        $this->assertCount(2, $assignments, 'A handover is two facts, not one edited row.');
        $this->assertTrue($assignments[0]->employee->is($first));
        $this->assertSame('2026-06-10', $assignments[0]->returned_on->toDateString());
        $this->assertTrue($assignments[1]->employee->is($second));
        $this->assertNull($assignments[1]->returned_on);
        $this->assertSame(Asset::ISSUED, $asset->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Exit clearance
    |--------------------------------------------------------------------------
    */

    public function test_what_a_leaver_still_has_to_hand_back(): void
    {
        $employee = $this->makeEmployee();

        $this->assets->issue($this->asset(['name' => 'Laptop']), $employee);
        $this->assets->issue($this->asset(['name' => 'Phone', 'type' => AssetTypes::PHONE]), $employee);

        $held = $this->assets->outstandingFor($employee);

        $this->assertCount(2, $held);
        $this->assertFalse($this->assets->isCleared($employee));

        // Give one back; one is still out.
        $this->assets->return(Asset::where('name', 'Laptop')->first());

        $this->assertCount(1, $this->assets->outstandingFor($employee->fresh()));
    }

    public function test_somebody_holding_nothing_is_cleared(): void
    {
        $employee = $this->makeEmployee();

        $this->assertTrue($this->assets->isCleared($employee));
        $this->assertTrue($this->assets->outstandingFor($employee)->isEmpty());
    }

    public function test_the_relieving_letter_screen_warns_about_outstanding_property(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $leaver = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_exit' => '2026-06-30']);

        $this->assets->issue($this->asset(['name' => 'ThinkPad X1']), $leaver);

        $this->actingAs($hr->user)
            ->get(route('letters.create', ['employee_id' => $leaver->id, 'type' => 'relieving']))
            ->assertOk()
            ->assertSee('still has')
            ->assertSee('ThinkPad X1');
    }

    public function test_the_warning_is_absent_when_nothing_is_outstanding(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $leaver = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_exit' => '2026-06-30']);

        $this->actingAs($hr->user)
            ->get(route('letters.create', ['employee_id' => $leaver->id, 'type' => 'relieving']))
            ->assertOk()
            ->assertDontSee('of company property');
    }

    /*
    |--------------------------------------------------------------------------
    | The screens
    |--------------------------------------------------------------------------
    */

    /**
     * Every asset screen renders.
     *
     * Posting to a form does not compile the page that draws it, so a Blade
     * error in the form itself passed a whole suite of green tests and was
     * only found by opening it. This opens all of them.
     */
    public function test_every_asset_screen_renders(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $employee);

        foreach ([
            route('assets.index'),
            route('assets.create'),
            route('assets.show', $asset),
            route('assets.edit', $asset),
        ] as $url) {
            $this->actingAs($hr->user)->get($url)->assertOk();
        }

        $this->actingAs($employee->user)->get(route('my-assets.index'))->assertOk();
        $this->actingAs($hr->user)->get(route('employees.show', $employee))->assertOk()->assertSee('Assets');
    }

    public function test_hr_can_add_an_asset_with_the_fields_its_kind_asks_for(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('assets.store'), [
            'asset_tag' => 'BS-9001',
            'type' => AssetTypes::SIM,
            'name' => 'Airtel connection',
            'condition' => 'new',
            'details' => ['mobile_number' => '+91 98800 12345', 'operator' => 'Airtel'],
        ])->assertRedirect();

        $asset = Asset::where('asset_tag', 'BS-9001')->firstOrFail();

        $this->assertSame(AssetTypes::SIM, $asset->type);
        $this->assertSame('+91 98800 12345', $asset->detail('mobile_number'));
        $this->assertSame(Asset::IN_STOCK, $asset->status, 'A new asset starts on the shelf.');
        $this->assertSame('+91 98800 12345', $asset->identifier(), 'A SIM is known by its number, not a serial.');
    }

    public function test_a_required_field_for_the_chosen_kind_is_enforced(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)->post(route('assets.store'), [
            'asset_tag' => 'BS-9002',
            'type' => AssetTypes::SIM,
            'name' => 'A connection with no number',
            'condition' => 'good',
        ])->assertSessionHasErrors('details.mobile_number');
    }

    public function test_issuing_and_returning_from_the_screen(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee();
        $asset = $this->asset();

        $this->actingAs($hr->user)->post(route('assets.issue', $asset), [
            'employee_id' => $employee->id,
            'issued_on' => '2026-06-10',
            'condition_out' => 'good',
        ])->assertRedirect();

        $this->assertSame(Asset::ISSUED, $asset->fresh()->status);

        $this->actingAs($hr->user)->post(route('assets.return', $asset), [
            'returned_on' => '2026-06-20',
            'condition_in' => 'good',
        ])->assertRedirect();

        $this->assertSame(Asset::IN_STOCK, $asset->fresh()->status);
    }

    public function test_an_employee_sees_what_they_hold_and_cannot_reach_the_register(): void
    {
        $employee = $this->makeEmployee();
        $asset = $this->asset(['name' => 'ThinkPad X1']);

        $this->assets->issue($asset, $employee);

        $this->actingAs($employee->user)->get(route('my-assets.index'))
            ->assertOk()
            ->assertSee('ThinkPad X1');

        $this->actingAs($employee->user)->get(route('assets.index'))->assertForbidden();
        $this->actingAs($employee->user)->post(route('assets.return', $asset), [
            'returned_on' => '2026-06-20', 'condition_in' => 'good',
        ])->assertForbidden();
    }

    public function test_an_asset_with_somebody_cannot_be_removed(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee();
        $asset = $this->asset();

        $this->assets->issue($asset, $employee);

        $this->actingAs($hr->user)->delete(route('assets.destroy', $asset))->assertStatus(422);
        $this->assertNotSoftDeleted($asset);
    }

    /*
    |--------------------------------------------------------------------------
    | The catalogue, and the message
    |--------------------------------------------------------------------------
    */

    public function test_every_kind_declares_what_it_needs(): void
    {
        foreach (AssetTypes::all() as $key => $type) {
            $this->assertNotEmpty($type['label'], $key.' has no label.');
            $this->assertNotEmpty($type['description'], $key.' has no description.');
            $this->assertIsBool($type['returnable']);

            foreach ($type['fields'] as $field => $definition) {
                $this->assertArrayHasKey('label', $definition, $key.'.'.$field);
                $this->assertContains($definition['type'], ['text', 'date', 'textarea'], $key.'.'.$field);
                $this->assertIsBool($definition['required'], $key.'.'.$field);
            }
        }
    }

    public function test_the_person_is_told_what_they_have_been_given(): void
    {
        $employee = $this->makeEmployee();
        $asset = $this->asset(['name' => 'ThinkPad X1']);

        $this->assets->issue($asset, $employee, null, ['condition_out' => 'new']);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $employee->user->id]);

        $notification = $employee->user->notifications()->first();

        $this->assertStringContainsString('ThinkPad X1', json_encode($notification->data));
        $this->assertSame(NotificationEvents::ASSET_ISSUED, $notification->data['type']);
    }
}
