<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\HelpArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SelfAssistanceTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------- the guides

    public function test_the_seeder_writes_a_guide_for_every_screen(): void
    {
        $this->seedReferenceData();
        $this->seed(HelpArticleSeeder::class);

        $articles = HelpArticle::all();

        $this->assertGreaterThanOrEqual(20, $articles->count());

        foreach ($articles as $article) {
            $this->assertNotEmpty($article->summary, $article->slug.' has no summary');
            $this->assertNotEmpty($article->body, $article->slug.' has no body');
            $this->assertContains($article->group, HelpArticle::GROUPS, $article->slug.' is in an unknown section');
        }
    }

    public function test_every_guide_points_at_a_real_screen_and_a_real_permission(): void
    {
        $this->seedReferenceData();
        $this->seed(HelpArticleSeeder::class);

        foreach (HelpArticle::all() as $article) {
            if ($article->route_name) {
                $this->assertTrue(
                    Route::has($article->route_name),
                    $article->slug.' points at a route that does not exist: '.$article->route_name,
                );
            }

            if ($article->permission) {
                $this->assertContains(
                    $article->permission,
                    Permissions::all(),
                    $article->slug.' requires a permission that is not in the catalogue',
                );
            }

            $this->assertFileExists(
                resource_path('views/components/icon/'.$article->icon.'.blade.php'),
                $article->slug.' uses an icon that does not exist: '.$article->icon,
            );
        }
    }

    public function test_re_running_the_seeder_restores_the_shipped_wording(): void
    {
        $this->seedReferenceData();
        $this->seed(HelpArticleSeeder::class);

        $article = HelpArticle::where('slug', 'dashboard')->firstOrFail();
        $shipped = $article->body;
        $article->update(['body' => 'Someone rewrote this.']);

        $this->seed(HelpArticleSeeder::class);

        $this->assertSame($shipped, $article->fresh()->body);
    }

    public function test_a_guide_written_in_house_survives_the_seeder(): void
    {
        $this->seedReferenceData();
        $this->seed(HelpArticleSeeder::class);

        HelpArticle::create([
            'slug' => 'our-own-policy',
            'group' => 'Administration',
            'title' => 'Our leave policy',
            'body' => 'Written by us.',
        ]);

        $this->seed(HelpArticleSeeder::class);

        $this->assertDatabaseHas('help_articles', ['slug' => 'our-own-policy']);
    }

    // -------------------------------------------------------------- the reader

    public function test_anyone_signed_in_can_read_the_guides(): void
    {
        $employee = $this->makeEmployee();
        $this->seed(HelpArticleSeeder::class);

        $this->actingAs($employee->user)
            ->get(route('self-assistance.index'))
            ->assertOk()
            ->assertSee('Self Assistance');

        $this->actingAs($employee->user)
            ->get(route('self-assistance.show', 'dashboard'))
            ->assertOk()
            ->assertSee('What is this page?');
    }

    public function test_signing_in_is_required(): void
    {
        $this->seedReferenceData();
        $this->seed(HelpArticleSeeder::class);

        $this->get(route('self-assistance.index'))->assertRedirect(route('login'));
    }

    public function test_a_guide_for_a_screen_you_cannot_open_is_hidden(): void
    {
        $employee = $this->makeEmployee();
        $this->seed(HelpArticleSeeder::class);

        // Payroll runs are not an employee's business.
        $this->actingAs($employee->user)
            ->get(route('self-assistance.index'))
            ->assertOk()
            ->assertDontSee('Payroll runs');

        $this->actingAs($employee->user)
            ->get(route('self-assistance.show', 'payroll-runs'))
            ->assertForbidden();
    }

    public function test_a_super_admin_sees_every_guide(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $this->seed(HelpArticleSeeder::class);

        $this->actingAs($admin->user)
            ->get(route('self-assistance.index'))
            ->assertOk()
            ->assertSee('Payroll runs')
            ->assertSee('Roles and permissions');
    }

    public function test_search_finds_a_guide_by_its_words(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $this->seed(HelpArticleSeeder::class);

        $this->actingAs($admin->user)
            ->get(route('self-assistance.index', ['q' => 'prorated']))
            ->assertOk()
            ->assertSee('Payroll runs');

        $this->actingAs($admin->user)
            ->get(route('self-assistance.index', ['q' => 'zzzz-nothing-matches']))
            ->assertOk()
            ->assertSee('Nothing matched');
    }

    public function test_a_draft_is_hidden_from_readers_but_visible_to_writers(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $admin->branch]);

        HelpArticle::create([
            'slug' => 'not-ready',
            'group' => 'Administration',
            'title' => 'Still being written',
            'body' => 'Half a sentence',
            'is_published' => false,
        ]);

        $this->actingAs($employee->user)->get(route('self-assistance.show', 'not-ready'))->assertNotFound();
        $this->actingAs($admin->user)->get(route('self-assistance.show', 'not-ready'))->assertOk();
    }

    public function test_the_header_offers_help_for_the_page_being_looked_at(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $this->seed(HelpArticleSeeder::class);

        $this->actingAs($admin->user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Help for this page')
            ->assertSee(route('self-assistance.show', 'employees'));
    }

    // --------------------------------------------------------------- managing

    public function test_only_a_writer_can_manage_the_guides(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->get(route('help-articles.index'))->assertForbidden();
        $this->actingAs($employee->user)->post(route('help-articles.store'), [])->assertForbidden();
    }

    public function test_a_guide_can_be_written_and_edited(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('help-articles.store'), [
            'title' => 'Our overtime policy',
            'group' => 'Administration',
            'icon' => 'clock',
            'summary' => 'When overtime is paid and who signs it off.',
            'body' => 'Overtime is paid at 1.5 times after nine hours.',
            'is_published' => '1',
            'fields' => [
                ['term' => 'Cut-off', 'description' => 'Nine hours in a day.'],
                ['term' => '', 'description' => 'This row is empty and should be dropped.'],
            ],
            'tasks' => [['question' => 'How do I claim it?', 'answer' => 'Raise an attendance correction.']],
        ])->assertRedirect();

        $article = HelpArticle::where('slug', 'our-overtime-policy')->firstOrFail();

        $this->assertSame('Our overtime policy', $article->title);
        $this->assertCount(1, $article->fields, 'The empty repeater row was saved.');
        $this->assertSame('Cut-off', $article->fields[0]['term']);
        $this->assertSame($admin->user->id, $article->updated_by);

        $this->actingAs($admin->user)->put(route('help-articles.update', $article), [
            'title' => 'Our overtime policy',
            'group' => 'Administration',
            'icon' => 'clock',
            'body' => 'Overtime is paid at twice the rate on a holiday.',
        ])->assertRedirect(route('help-articles.index'));

        $this->assertStringContainsString('twice the rate', $article->fresh()->body);
    }

    public function test_a_guide_cannot_claim_a_screen_or_permission_that_does_not_exist(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('help-articles.store'), [
            'title' => 'Nonsense',
            'group' => 'Administration',
            'icon' => 'not-a-real-icon',
            'route_name' => 'no.such.route',
            'permission' => 'no.such.permission',
        ])->assertSessionHasErrors(['icon', 'route_name', 'permission']);

        $this->assertDatabaseCount('help_articles', 0);
    }

    public function test_a_guide_is_never_rendered_as_markup(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $article = HelpArticle::create([
            'slug' => 'escaping',
            'group' => 'Administration',
            'title' => 'Escaping',
            'body' => 'Careful now <script>alert(1)</script>',
        ]);

        $response = $this->actingAs($admin->user)
            ->get(route('self-assistance.show', $article))
            ->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_a_guide_can_be_deleted(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        $article = HelpArticle::create([
            'slug' => 'temporary', 'group' => 'Administration', 'title' => 'Temporary',
        ]);

        $this->actingAs($admin->user)
            ->delete(route('help-articles.destroy', $article))
            ->assertRedirect(route('help-articles.index'));

        $this->assertDatabaseCount('help_articles', 0);
    }
}
