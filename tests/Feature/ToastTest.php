<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Messages and questions, and the browser dialogs they replaced.
 *
 * The toaster itself is JavaScript and is exercised in a browser. What can be
 * held to account from here is the part that decides *what* is said: the
 * payload the server hands the page, and the promise that no native dialog has
 * crept back in.
 */
class ToastTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Nothing native, anywhere
    |--------------------------------------------------------------------------
    */

    /**
     * No `alert()` or `confirm()` in anything the browser runs.
     *
     * This is the test that stops the old habit coming back one view at a
     * time. It reads the files rather than the rendered page, because a
     * dialog on a screen no test happens to visit is still a dialog.
     */
    public function test_no_native_browser_dialogs_survive_anywhere(): void
    {
        $offenders = [];

        $files = array_merge(
            File::allFiles(resource_path('views')),
            File::allFiles(resource_path('js')),
        );

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                // Comments are allowed to name what was removed.
                $code = trim($line);

                if (str_starts_with($code, '*') || str_starts_with($code, '//') || str_starts_with($code, '{{--')) {
                    continue;
                }

                if (preg_match('/(?<![\w$.>])(?:window\s*\.\s*)?(alert|confirm)\s*\(/', $line, $match)) {
                    $offenders[] = sprintf(
                        '%s:%d uses %s()',
                        str_replace(base_path().'/', '', $file->getPathname()),
                        $number + 1,
                        $match[1],
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Native browser dialogs are gone; use showToast() or showConfirmToast() instead.\n"
                .implode("\n", $offenders),
        );
    }

    public function test_the_shell_still_offers_a_way_to_ask(): void
    {
        $toast = File::get(resource_path('js/toast.js'));

        foreach (['export function showToast', 'export function showConfirmToast'] as $signature) {
            $this->assertStringContainsString($signature, $toast);
        }

        // A question must never be trimmed away by a burst of notices: the
        // promise behind it would never settle and a delete would hang.
        $this->assertStringContainsString('live.filter((e) => !e.isConfirm)', $toast);
    }

    /*
    |--------------------------------------------------------------------------
    | What the server says
    |--------------------------------------------------------------------------
    */

    public function test_a_flashed_message_arrives_as_a_toast_not_a_banner(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $holiday = Holiday::create([
            'name' => 'Toaster Day', 'date' => '2026-12-24', 'type' => 'public',
        ]);

        // With a referer, back() lands on the page that asked. Without one it
        // bounces via "/" first, and a flash only survives a single hop.
        $response = $this->actingAs($admin->user)
            ->from(route('holidays.index'))
            ->delete(route('holidays.destroy', $holiday))
            ->assertRedirect();

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('data-toast-payload', escape: false)
            ->assertSee('Holiday removed');
    }

    public function test_a_validation_failure_says_so_in_the_payload(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $response = $this->actingAs($admin->user)
            ->from(route('holidays.index'))
            ->post(route('holidays.store'), ['name' => '', 'date' => '', 'type' => 'nonsense']);

        // Fields are still marked individually; the toast is for the case the
        // offending field is scrolled off the screen.
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('data-toast-payload', escape: false)
            ->assertSee('need attention');
    }

    public function test_a_single_error_is_quoted_rather_than_counted(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $response = $this->actingAs($admin->user)
            ->from(route('holidays.index'))
            ->post(route('holidays.store'), [
                'name' => 'Fine', 'date' => 'not-a-date', 'type' => 'public',
            ]);

        $page = $this->followRedirects($response)->assertOk();

        $page->assertSee('data-toast-payload', escape: false);
        $page->assertDontSee('need attention');
    }

    public function test_the_toaster_is_present_signed_in_and_signed_out(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-toaster', escape: false)
            ->assertSee('aria-live="polite"', escape: false);

        // Signed out too: a password reset says what happened on that screen.
        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-toaster', escape: false);
    }

    public function test_every_destructive_button_still_asks_first(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        Holiday::create(['name' => 'Something deletable', 'date' => now()->toDateString(), 'type' => 'public']);

        // A screen with a delete on it must carry the attribute the confirm
        // handler watches for — otherwise the button deletes on one click.
        $this->actingAs($admin->user)->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('data-confirm', escape: false);
    }
}
