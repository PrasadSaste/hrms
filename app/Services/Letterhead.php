<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Setting;
use App\Models\Signatory;
use Illuminate\Support\Facades\Storage;

/**
 * Whose name goes at the top of a document.
 *
 * A payslip and a letter both go out on the letterhead of the legal entity
 * that employs the person, not the group's, so the same answer serves both.
 * A document created before companies existed has none, and falls back to the
 * organisation settings so an old payslip still prints correctly.
 */
class Letterhead
{
    /** @return array<string, string|null> */
    public function forCompany(?Company $company = null): array
    {
        if (! $company) {
            return [
                'name' => Setting::get('company_name', config('app.name')),
                'address' => Setting::get('company_address'),
                'address_lines' => $this->lines(Setting::get('company_address')),
                'email' => Setting::get('company_email'),
                'phone' => Setting::get('company_phone'),
                'website' => Setting::get('company_website'),
                'tax_id' => Setting::get('company_tax_id'),
                'registration_number' => null,
                'pf_number' => null,
                'esi_number' => null,
                'signatory_name' => null,
                'signatory_designation' => null,
                'signature_src' => null,
                'logo' => Setting::get('company_logo'),
                'logo_src' => $this->logoDataUri(Setting::get('company_logo')),
                'footer' => null,
                // A document with no company at all still says who issued it.
                'watermark' => (string) Setting::get('company_name', config('app.name')) ?: null,
                'brand_color' => app(BrandPalette::class)->color(),
                'watermark_color' => app(BrandPalette::class)->ramp()[100],
            ];
        }

        // Whoever signs for this entity, from its own list. A company that
        // predates the list still has the single name on its record.
        $signatory = $company->defaultSignatory();

        return [
            'name' => $company->displayName(),
            'address' => implode(', ', $company->addressLines()) ?: Setting::get('company_address'),
            // The pad prints one line per element: a letterhead address is a
            // block, not a comma-separated run-on.
            'address_lines' => $company->addressLines()
                ?: $this->lines(Setting::get('company_address')),
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'tax_id' => $company->tax_id,
            'registration_number' => $company->registration_number,
            'pf_number' => $company->pf_number,
            'esi_number' => $company->esi_number,
            'signatory_name' => $signatory?->name ?: $company->signatory_name,
            'signatory_designation' => $signatory?->designation ?: $company->signatory_designation,
            'signature_src' => $signatory ? Signatory::signatureDataUri($signatory->signature_path) : null,
            'logo' => $company->letterhead_logo_path ?: $company->logo_path,
            // The print logo if one is set, otherwise the one used on screen.
            // Deliberately not the group's: a second legal entity printing the
            // parent's logo on a payslip is worse than printing its name.
            'logo_src' => $this->logoDataUri(
                $company->letterhead_logo_path ?: $company->logo_path,
                fallBackToGroup: false,
            ),
            'footer' => $company->letterhead_footer,
            'watermark' => $company->watermark(),
            'brand_color' => app(BrandPalette::class)->color(),
            // The palest step of the theme: visible as a tint on paper, never
            // dark enough to fight the words printed over it.
            'watermark_color' => app(BrandPalette::class)->ramp()[100],
        ];
    }

    /**
     * Free text split into the lines it was typed as.
     *
     * @return array<int, string>
     */
    protected function lines(?string $text): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) $text) ?: [],
        ), fn (string $line) => $line !== ''));
    }

    /**
     * The logo as a data URI. DomPDF renders offline and will not fetch over
     * the network, so the bytes have to travel with the document.
     */
    public function logoDataUri(?string $path = null, bool $fallBackToGroup = true): ?string
    {
        // A company with no logo of its own prints its name, not the group's
        // mark: a second legal entity carrying the parent's logo misstates who
        // issued the document.
        if ($fallBackToGroup) {
            $path ??= Setting::get('company_logo');
        }

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        try {
            $bytes = Storage::disk('public')->get($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        } catch (\Throwable) {
            return null;
        }

        // SVG is not rasterised reliably by DomPDF, so skip it rather than
        // print a broken box on every document.
        if (str_contains($mime, 'svg')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
