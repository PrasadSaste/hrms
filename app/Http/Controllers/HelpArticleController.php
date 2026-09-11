<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Writing and editing the Self Assistance guides.
 *
 * The seeder ships one guide per screen; this is where a company makes them
 * their own — their wording, their policies, their own extra pages.
 */
class HelpArticleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('help-articles.index', [
            'articles' => HelpArticle::query()->ordered()->get()->groupBy('group'),
            'groups' => HelpArticle::GROUPS,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorise($request);

        return view('help-articles.edit', [
            'article' => new HelpArticle(['group' => HelpArticle::GROUPS[0], 'icon' => 'document', 'is_published' => true]),
            'groups' => HelpArticle::GROUPS,
            'icons' => $this->icons(),
            'routes' => $this->routes(),
            'permissions' => Permissions::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $article = HelpArticle::create($this->validated($request));

        return redirect()->route('help-articles.edit', $article)
            ->with('success', 'Guide created.');
    }

    public function edit(Request $request, HelpArticle $article): View
    {
        $this->authorise($request);

        return view('help-articles.edit', [
            'article' => $article,
            'groups' => HelpArticle::GROUPS,
            'icons' => $this->icons(),
            'routes' => $this->routes(),
            'permissions' => Permissions::all(),
        ]);
    }

    public function update(Request $request, HelpArticle $article): RedirectResponse
    {
        $this->authorise($request);

        $article->update($this->validated($request, $article));

        return redirect()->route('help-articles.index')
            ->with('success', 'Saved “'.$article->title.'”.');
    }

    public function destroy(Request $request, HelpArticle $article): RedirectResponse
    {
        $this->authorise($request);

        $title = $article->title;
        $article->delete();

        return redirect()->route('help-articles.index')
            ->with('success', 'Deleted “'.$title.'”. Re-run the guide seeder to bring the shipped one back.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?HelpArticle $article = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('help_articles', 'slug')->ignore($article?->id),
            ],
            'group' => ['required', 'string', 'max:64'],
            'icon' => ['required', 'string', Rule::in($this->icons())],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'route_name' => ['nullable', 'string', Rule::in($this->routes())],
            'permission' => ['nullable', 'string', Rule::in(Permissions::all())],
            'position' => ['nullable', 'integer', 'between:0,999'],
            'is_published' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.term' => ['nullable', 'string', 'max:120'],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.question' => ['nullable', 'string', 'max:200'],
            'tasks.*.answer' => ['nullable', 'string', 'max:4000'],
            'troubleshooting' => ['nullable', 'array'],
            'troubleshooting.*.problem' => ['nullable', 'string', 'max:200'],
            'troubleshooting.*.answer' => ['nullable', 'string', 'max:4000'],
        ]);

        return [
            ...$validated,
            'slug' => ($validated['slug'] ?? null) ?: Str::slug($validated['title']),
            'position' => $validated['position'] ?? 0,
            'is_published' => $request->boolean('is_published'),
            // A row where the reader-facing half is blank is a leftover from
            // the repeating form, not something anyone meant to save.
            'fields' => $this->rows($request->input('fields'), 'term'),
            'tasks' => $this->rows($request->input('tasks'), 'question'),
            'troubleshooting' => $this->rows($request->input('troubleshooting'), 'problem'),
            'updated_by' => $request->user()->id,
        ];
    }

    /** Drop empty repeater rows and re-index what is left. */
    protected function rows(mixed $rows, string $required): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row) => filled($row[$required] ?? null))
            ->map(fn ($row) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row))
            ->values()
            ->all();
    }

    /** The icon components a guide may use. */
    protected function icons(): array
    {
        return collect(glob(resource_path('views/components/icon/*.blade.php')))
            ->map(fn (string $path) => basename($path, '.blade.php'))
            ->sort()
            ->values()
            ->all();
    }

    /** Named GET routes a guide can point at, so "Open the screen" works. */
    protected function routes(): array
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn ($route) => $route->getName()
                && in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')
                && ! str_starts_with($route->uri(), 'api/'))
            ->map(fn ($route) => $route->getName())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function authorise(Request $request): void
    {
        abort_unless($request->user()->can('help.manage'), 403);
    }
}
