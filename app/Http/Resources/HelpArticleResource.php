<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Self Assistance guide.
 *
 * The body goes out as both Markdown and rendered HTML: a web client can drop
 * the HTML straight in, and a native one can style the Markdown itself rather
 * than embedding a browser to do it.
 */
class HelpArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'group' => $this->group,
            'title' => $this->title,
            'summary' => $this->summary,
            'route_name' => $this->route_name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Only on a single guide: a list of forty bodies is a large
            // response nobody reads.
            'body' => $this->when($request->routeIs('api.help.show'), fn () => $this->body),
            'body_html' => $this->when($request->routeIs('api.help.show'), fn () => $this->bodyHtml()),
        ];
    }
}
