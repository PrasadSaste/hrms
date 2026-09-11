<?php

namespace App\Http\Resources;

use App\Models\BackgroundCheckItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A joiner's verification case and the checklist under it.
 *
 * `fields` beside each item is what the requirement asks to be typed in
 * alongside the document, so a client can draw the form without hard-coding a
 * copy of `BackgroundCheckRequirements`.
 */
class BackgroundCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'open_to_employee' => $this->isOpenToEmployee(),
            'awaiting_review' => $this->isAwaitingReview(),
            'verified' => $this->isVerified(),
            'due_on' => $this->due_on?->toDateString(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_remarks' => $this->review_remarks,
            'ready_to_submit' => $this->whenLoaded('items', fn () => $this->isReadyToSubmit()),
            'items' => $this->whenLoaded(
                'items',
                fn () => $this->items->map(fn (BackgroundCheckItem $item) => $this->item($item)),
            ),
        ];
    }

    /** @return array<string, mixed> */
    protected function item(BackgroundCheckItem $item): array
    {
        return [
            'id' => $item->id,
            'requirement' => $item->requirement,
            'label' => $item->label(),
            'description' => $item->description(),
            'status' => $item->status,
            'status_label' => $item->statusLabel(),
            'is_required' => $item->is_required,
            'has_upload' => $item->hasUpload(),
            'needs_work' => $item->needsWork(),
            'file_name' => $item->file_name,
            'size' => $item->size,
            'human_size' => $item->hasUpload() ? $item->humanSize() : null,
            'uploaded_at' => $item->uploaded_at?->toIso8601String(),
            'remarks' => $item->remarks,
            'details' => $item->details ?? [],
            // What to ask for beside the file, straight from the catalogue.
            'fields' => collect($item->fields())->map(fn (array $field, string $key) => [
                'name' => $key,
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => $field['required'],
            ])->values(),
            'upload_url' => route('api.background-check.upload', $item->id),
            'document_url' => $item->hasUpload() ? route('api.background-check.document', $item->id) : null,
        ];
    }
}
