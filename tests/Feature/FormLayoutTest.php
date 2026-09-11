<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Reaching Save without hunting for it.
 *
 * A form taller than the screen used to hide its own submit button at the
 * bottom — worst of all on the notification wording, which took 785 pixels of
 * scrolling before Save came into view. `<x-form-actions>` sticks to the foot
 * of the viewport while there is still form below it and settles at the end
 * when there is not.
 *
 * How it *looks* is a browser's business. What can be held to account here is
 * that the long forms actually carry the bar, and that nothing else regressed.
 */
class FormLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    public static function longForms(): array
    {
        return [
            'new employee' => ['employees.create', ''],
            'new asset' => ['assets.create', ''],
            'new announcement' => ['announcements.create', ''],
            'new payroll run' => ['payroll.create', ''],
            'new salary structure' => ['salary-structures.create', ''],
        ];
    }

    public function test_the_long_forms_all_carry_a_sticky_action_bar(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        foreach (self::longForms() as [$route]) {
            $this->actingAs($admin->user)->get(route($route))
                ->assertOk()
                ->assertSee('data-form-actions', escape: false);
        }
    }

    public function test_a_form_that_edits_something_carries_it_too(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = Company::firstOrFail();

        $this->actingAs($admin->user)->get(route('companies.edit', $company))
            ->assertOk()
            ->assertSee('data-form-actions', escape: false)
            ->assertSee('Save changes');

        $this->actingAs($admin->user)->get(route('letter-templates.edit', 'appointment'))
            ->assertOk()
            ->assertSee('data-form-actions', escape: false);

        $this->actingAs($admin->user)->get(route('notification-templates.edit', 'leave.approved'))
            ->assertOk()
            ->assertSee('data-form-actions', escape: false);
    }

    /**
     * The bar sticks by itself.
     *
     * `position: sticky` is what puts Save within reach; the JavaScript only
     * adds a shadow while it floats. If the CSS ever loses the rule the
     * behaviour is gone and nothing else would notice.
     */
    public function test_the_bar_sticks_without_javascript(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.form-actions\s*\{[^}]*position:\s*sticky/s',
            $css,
            'The action bar no longer sticks on its own.',
        );

        $this->assertMatchesRegularExpression(
            '/\.form-actions\s*\{[^}]*bottom:\s*0/s',
            $css,
            'A bar that sticks to the top is not what anybody needs.',
        );

        // It is a screen affordance; on paper it is just the end of the form.
        $this->assertMatchesRegularExpression(
            '/@media print.*\.form-actions\s*\{[^}]*position:\s*static/s',
            $css,
        );
    }

    public function test_saving_still_works_from_the_bar(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $company = Company::firstOrFail();

        // Moving the button did not move the form: the same post still saves.
        $this->actingAs($admin->user)
            ->from(route('companies.edit', $company))
            ->put(route('companies.update', $company), [
                'name' => 'Renamed From The Bar',
                'code' => $company->code,
                'currency' => 'INR',
                'payslip_prefix' => $company->payslip_prefix ?: 'PS',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Renamed From The Bar', $company->fresh()->name);
    }
}
