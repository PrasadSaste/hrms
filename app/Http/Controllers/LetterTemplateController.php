<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\LetterService;
use App\Support\LetterTypes;
use App\Support\Markdown;
use App\Support\Placeholders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The wording of each kind of letter.
 *
 * Editing here changes what the *next* letter will say. Letters already issued
 * carry their own words and are untouched, which is the whole point of freezing
 * them at issue.
 */
class LetterTemplateController extends Controller
{
    public function __construct(protected LetterService $letters) {}

    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('letter-templates.index', [
            'grouped' => LetterTypes::grouped(),
            'customised' => collect(LetterTypes::keys())
                ->mapWithKeys(fn (string $type) => [$type => LetterTemplate::isCustomised($type)]),
            'issued' => Letter::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    public function edit(Request $request, string $type): View
    {
        $this->authorise($request);
        $definition = $this->findOrFail($type);

        return view('letter-templates.edit', [
            'type' => $type,
            'definition' => $definition,
            'template' => LetterTemplate::resolve($type),
            'placeholders' => LetterTypes::placeholders($type),
            'customised' => LetterTemplate::isCustomised($type),
            'issued' => Letter::where('type', $type)->count(),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $this->authorise($request);
        $definition = $this->findOrFail($type);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        LetterTemplate::updateOrCreate(['type' => $type], [
            // Wording identical to the shipped default is stored as nothing, so
            // the letter keeps following the catalogue if that ever improves.
            'subject' => $this->overrideOrNull($definition, 'subject', $validated['subject'] ?? null),
            'body' => $this->overrideOrNull($definition, 'body', $validated['body'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('letter-templates.index')
            ->with('success', 'Saved the wording for the '.strtolower($definition['label']).'.');
    }

    /** Put a letter back to the wording it shipped with. */
    public function destroy(Request $request, string $type): RedirectResponse
    {
        $this->authorise($request);
        $definition = $this->findOrFail($type);

        LetterTemplate::where('type', $type)->first()?->delete();

        return redirect()->route('letter-templates.edit', $type)
            ->with('success', 'The '.strtolower($definition['label']).' is back to its original wording.');
    }

    /** What the wording on screen would look like, with sample values. */
    public function preview(Request $request, string $type): JsonResponse
    {
        $this->authorise($request);
        $this->findOrFail($type);

        $template = $this->letters->template($type, [
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
        ]);

        $sample = $this->letters->sampleData($type);

        return response()->json([
            'subject' => Placeholders::render($template['subject'], $sample, fn () => ''),
            'html' => Markdown::html(
                Placeholders::render($template['body'], $sample, fn () => '—'),
                hardBreaks: true,
            ),
        ]);
    }

    protected function findOrFail(string $type): array
    {
        $definition = LetterTypes::find($type);

        abort_unless($definition, 404);

        return $definition;
    }

    protected function overrideOrNull(array $definition, string $field, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim($value) === trim((string) ($definition[$field] ?? '')) ? null : $value;
    }

    protected function authorise(Request $request): void
    {
        abort_unless($request->user()->can('letters.manage-templates'), 403);
    }
}
