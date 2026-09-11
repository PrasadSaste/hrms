<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\HelpArticle;
use App\Services\NotificationTemplateRenderer;
use App\Support\LetterTypes;
use App\Support\Markdown;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rich text editor writes Markdown, and every surface renders it.
 *
 * The editor is browser-side, so what is checked here is the contract it
 * depends on: whatever it can produce, the renderer must render, and the
 * field must carry it through a save unchanged.
 */
class RichTextTest extends TestCase
{
    use RefreshDatabase;

    /** Everything the toolbar can write, as the editor serialises it. */
    protected function everyFeature(): string
    {
        return <<<'MD'
            # Heading

            ## Subheading

            ### Small heading

            Plain text with **bold**, *italic*, ~~struck~~ and `code`.

            - A bullet
            - Another bullet

            1. First
            2. Second

            - [ ] Not done
            - [x] Done

            > A quote

            | Team | Days |
            | --- | --- |
            | Engineering | Tue-Thu |

            ---

            ```
            php artisan queue:work
            ```

            A [link](https://example.com).
            MD;
    }

    public function test_the_renderer_renders_everything_the_toolbar_can_write(): void
    {
        $html = Markdown::html($this->everyFeature());

        // Nothing the editor offers may be silently dropped on the way out.
        foreach ([
            '<h1>', '<h2>', '<h3>',
            '<strong>', '<em>', '<del>', '<code>',
            '<ul>', '<ol>', '<li>',
            'type="checkbox"',
            '<blockquote>',
            '<table>', '<th>', '<td>',
            '<hr />',
            '<pre>',
            'href="https://example.com"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle.' was not rendered.');
        }
    }

    public function test_raw_markup_is_still_escaped(): void
    {
        // The editor writes Markdown, but the field accepts anything typed or
        // pasted, and a script in an announcement must never run.
        $html = Markdown::html('Hello <script>alert(1)</script> there');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_an_announcement_keeps_its_formatting_through_a_save(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $body = $this->everyFeature();

        $this->actingAs($admin->user)->post(route('announcements.store'), [
            'title' => 'Revised hybrid working policy',
            'body' => $body,
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame($body, Announcement::latest('id')->first()->body);
    }

    public function test_an_announcement_renders_its_markdown_on_screen(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $announcement = Announcement::create([
            'title' => 'Revised hybrid working policy',
            'body' => "**Three days** in the office.\n\n| Team | Days |\n| --- | --- |\n| Engineering | Tue-Thu |",
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->user->id,
        ]);

        $this->actingAs($admin->user)->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('<strong>Three days</strong>', escape: false)
            ->assertSee('<table>', escape: false);
    }

    public function test_an_announcement_written_before_the_editor_still_reads_the_same(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        // Plain text with line breaks, as the field used to hold. Hard breaks
        // keep every one of them, so nothing already written changes shape.
        $announcement = Announcement::create([
            'title' => 'Office closed',
            'body' => "The office is closed on Friday.\nPlease work from home.\nThank you.",
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->user->id,
        ]);

        $this->actingAs($admin->user)->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('The office is closed on Friday.<br />', escape: false)
            ->assertSee('Please work from home.<br />', escape: false);
    }

    public function test_the_editor_is_offered_on_the_announcement_form(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->get(route('announcements.create'))
            ->assertOk()
            ->assertSee('data-rich-text', escape: false)
            // A few of the newer buttons, so a toolbar cut back by accident fails.
            ->assertSee('data-rich-action="strike"', escape: false)
            ->assertSee('data-rich-action="table"', escape: false)
            ->assertSee('data-rich-action="tasks"', escape: false)
            ->assertSee('data-rich-action="codeblock"', escape: false);
    }

    public function test_the_editor_is_offered_on_the_letter_wording(): void
    {
        $admin = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($admin->user)
            ->get(route('letter-templates.edit', LetterTypes::OFFER))
            ->assertOk()
            ->assertSee('data-rich-text', escape: false)
            // A letter keeps every line where the author put it, so the editor
            // must be told the renderer breaks on each one.
            ->assertSee('data-hard-breaks', escape: false);
    }

    public function test_the_editor_is_offered_on_a_help_guide(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $article = HelpArticle::create([
            'slug' => 'a-guide',
            'group' => 'People',
            'title' => 'A guide',
            'route_name' => 'dashboard',
            'body' => 'Something.',
            'status' => 'published',
        ]);

        $response = $this->actingAs($admin->user)
            ->get(route('help-articles.edit', $article))
            ->assertOk()
            ->assertSee('data-rich-text', escape: false);

        // A guide is prose: a wrapped paragraph reads as one paragraph, so the
        // editor must flow it rather than break on every line.
        $this->assertStringNotContainsString('data-hard-breaks', $response->getContent());
    }

    public function test_a_block_of_details_arrives_on_separate_lines(): void
    {
        // These read as one run-on line when the renderer collapses newlines,
        // which is what every notification email used to do.
        $html = app(NotificationTemplateRenderer::class)->html(
            "**Reference:** LR-1\n**Working days:** 3\n**Decided by:** Priya",
        );

        $this->assertSame(2, substr_count($html, '<br />'), 'The details ran together.');
    }

    public function test_the_notification_catalogue_writes_prose_as_one_line(): void
    {
        // With hard breaks on, a sentence wrapped across two source lines would
        // be chopped in half in the email.
        foreach (NotificationEvents::keys() as $key) {
            $body = NotificationEvents::find($key)['email']['body'] ?? null;

            if (! $body) {
                continue;
            }

            foreach (explode("\n\n", $body) as $paragraph) {
                $lines = explode("\n", $paragraph);

                if (count($lines) < 2) {
                    continue;
                }

                $structural = collect($lines)->contains(fn (string $line) => $line === ''
                    || preg_match('/^(#|>|\||```)/', trim($line)) === 1
                    || preg_match('/^[-*+]\s/', trim($line)) === 1
                    || preg_match('/^\d+[.)]\s/', trim($line)) === 1
                    || preg_match('/^\*\*[^*]+:\*\*/', trim($line)) === 1
                    || str_ends_with(trim($line), ':'));

                $this->assertTrue(
                    $structural,
                    "The {$key} template wraps a prose paragraph, which hard breaks would chop in half.",
                );
            }
        }
    }

    public function test_the_editor_is_offered_on_the_notification_wording(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('notification-templates.edit', 'leave.approved'))
            ->assertOk()
            ->assertSee('data-rich-text', escape: false)
            ->assertSee('data-rich-table-tools', escape: false);
    }
}
