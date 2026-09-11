<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BrandPalette;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Serves the company logo and browser icon straight from the storage disk.
 *
 * Going through a route rather than the public symlink means they show up on a
 * fresh deployment whether or not anyone remembered `storage:link`, and it lets
 * the sign-in page carry the branding without being authenticated.
 */
class BrandingController extends Controller
{
    /**
     * An uploaded logo may be an SVG, which is a document rather than a
     * picture: served plainly it could run script on this origin. These headers
     * let the image draw and nothing else.
     */
    private const SAFE_HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        'X-Content-Type-Options' => 'nosniff',
        'Content-Disposition' => 'inline',
    ];

    public function logo(): BaseResponse
    {
        $path = Setting::get('company_logo');

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return $this->file($path);
    }

    /**
     * The browser tab icon.
     *
     * Until one is uploaded this draws the company initials on the brand
     * colour, so a tab is recognisable from the first day rather than showing
     * the browser's blank page icon.
     */
    public function favicon(): BaseResponse
    {
        $path = Setting::get('company_favicon');

        if ($path && Storage::disk('public')->exists($path)) {
            return $this->file($path);
        }

        return new Response($this->generatedIcon(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ] + self::SAFE_HEADERS);
    }

    protected function file(string $path): BaseResponse
    {
        $bytes = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return new Response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            // The URL carries a cache-busting token, so this can be cached hard.
            'Cache-Control' => 'public, max-age=604800',
            'ETag' => '"'.md5($path.strlen($bytes)).'"',
        ] + self::SAFE_HEADERS);
    }

    /**
     * A rounded square carrying the company initials, drawn in the primary and
     * secondary theme colours so the tab icon matches the interface.
     */
    protected function generatedIcon(): string
    {
        $palette = app(BrandPalette::class);
        $company = (string) Setting::get('company_name', config('app.name'));

        $initials = collect(preg_split('/[\s\-]+/', trim($company)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('') ?: 'HR';

        $primary = $palette->color('primary');
        $secondary = $palette->color('secondary');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
                <defs>
                    <linearGradient id="mark" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="{$primary}"/>
                        <stop offset="100%" stop-color="{$secondary}"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" rx="14" fill="url(#mark)"/>
                <text x="32" y="33" fill="{$palette->foregroundOn()}" font-size="30" font-weight="700"
                    font-family="system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
                    text-anchor="middle" dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;
    }
}
