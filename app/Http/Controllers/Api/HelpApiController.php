<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpArticleResource;
use App\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Self Assistance guides, for a client that wants a help screen of its own.
 *
 * A reader only ever sees a guide to a screen they could actually open, so the
 * same `readableBy` scope the web reader uses decides this too — an API is not
 * a way around a permission.
 */
class HelpApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->query('q', ''));

        $articles = HelpArticle::query()
            ->published()
            ->readableBy($user)
            ->ordered()
            ->get();

        if ($query !== '') {
            $needle = Str::lower($query);
            $articles = $articles
                ->filter(fn (HelpArticle $a) => str_contains($a->searchableText(), $needle))
                ->values();
        }

        return response()->json([
            'data' => $articles
                ->groupBy('group')
                ->map(fn ($group) => HelpArticleResource::collection($group)->resolve($request))
                ->all(),
            'meta' => ['total' => $articles->count(), 'query' => $query],
        ]);
    }

    public function show(Request $request, HelpArticle $article): JsonResponse
    {
        $user = $request->user();

        abort_unless($article->is_published || $user?->can('help.manage'), 404);
        abort_unless(
            $article->permission === null || $user?->can($article->permission),
            403,
            'This guide covers a screen your account does not have access to.',
        );

        return response()->json(['data' => new HelpArticleResource($article)]);
    }
}
