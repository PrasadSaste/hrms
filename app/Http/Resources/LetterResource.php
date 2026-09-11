<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A letter as it was issued.
 *
 * Every word here is the frozen copy on the `letters` row, never a fresh
 * render of today's template, so what a client shows is what was signed.
 */
class LetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'subject' => $this->subject,
            'issued_on' => $this->issued_on?->toDateString(),
            'effective_from' => $this->effective_from?->toDateString(),
            'signatory_name' => $this->signatory_name,
            'signatory_designation' => $this->signatory_designation,
            'emailed' => $this->wasEmailed(),
            'download_url' => route('api.letters.download', $this->id),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'legal_name' => $this->company->legal_name,
            ]),
            // Only on a single letter: the body is long, and a list of
            // twenty would be mostly prose nobody asked for.
            'body' => $this->when($request->routeIs('api.letters.show'), fn () => $this->body),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}
