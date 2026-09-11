<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Self Assistance guide: one page per screen, written for the people who
 * run the system rather than for whoever built it.
 */
class SelfAssistanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = trim((string) $request->query('q', ''));

        return view('self-assistance.index', [
            'groups' => HelpArticle::grouped($user),
            'query' => $query,
            'results' => $query === '' ? collect() : $this->search($request, $query),
            'current' => null,
        ]);
    }

    public function show(Request $request, HelpArticle $article): View
    {
        $user = $request->user();

        // A guide to a screen someone cannot open is not shown to them, and an
        // unpublished draft is only visible to whoever may edit it.
        abort_unless(
            $article->is_published || $user?->can('help.manage'),
            404,
        );
        abort_unless(
            $article->permission === null || $user?->can($article->permission),
            403,
            'This guide covers a screen your account does not have access to.',
        );

        return view('self-assistance.show', [
            'groups' => HelpArticle::grouped($user),
            'article' => $article,
            'current' => $article,
            'query' => '',
            'neighbours' => $this->neighbours($article, $user),
        ]);
    }

    /**
     * Plain substring search across every guide the reader may see.
     *
     * The guide set is small enough that scanning it in PHP is quicker than
     * maintaining an index, and it behaves the same on SQLite and MySQL.
     */
    protected function search(Request $request, string $query): Collection
    {
        $needle = Str::lower($query);

        return HelpArticle::query()
            ->published()
            ->readableBy($request->user())
            ->ordered()
            ->get()
            ->filter(fn (HelpArticle $article) => str_contains($article->searchableText(), $needle))
            ->map(fn (HelpArticle $article) => [
                'article' => $article,
                'excerpt' => $this->excerptAround($article, $needle),
            ])
            ->values();
    }

    /** The words around the match, so a result shows why it matched. */
    protected function excerptAround(HelpArticle $article, string $needle): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) Markdown::excerpt($article->body, 5000)) ?? '');
        $position = mb_stripos($text, $needle);

        if ($text === '' || $position === false) {
            return (string) Str::limit($article->summary ?? $text, 160);
        }

        $start = max(0, $position - 60);

        return ($start > 0 ? '… ' : '').Str::limit(mb_substr($text, $start), 180);
    }

    /** The previous and next guide, so a reader can work straight through. */
    protected function neighbours(HelpArticle $article, $user): array
    {
        $flat = HelpArticle::grouped($user)->flatten();
        $index = $flat->search(fn (HelpArticle $a) => $a->is($article));

        return [
            'previous' => $index === false ? null : $flat->get($index - 1),
            'next' => $index === false ? null : $flat->get($index + 1),
        ];
    }
}
