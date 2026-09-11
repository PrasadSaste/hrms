<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackgroundCheckResource;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Services\BackgroundCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A joiner's own side of background verification, over the API.
 *
 * This is the one part of the system somebody uses before they properly work
 * here — often from a phone, often before they have a desk — so it being
 * web-only was the wrong way round. None of it sits behind `bgv.cleared`, for
 * the obvious reason that clearing it is the point.
 */
class BackgroundCheckApiController extends Controller
{
    public function __construct(protected BackgroundCheckService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new BackgroundCheckResource($this->check($request)->load(['items', 'reviewer'])),
        ]);
    }

    /**
     * Upload or replace one document, with the details asked for beside it.
     *
     * The file is optional on a second save, so a correction to a typed date
     * does not mean sending the whole scan again over mobile data.
     */
    public function upload(Request $request, BackgroundCheckItem $item): JsonResponse
    {
        $check = $this->check($request);

        abort_unless($item->background_check_id === $check->id, 403);
        abort_unless($check->isOpenToEmployee(), 403, 'This verification has already been submitted.');

        $rules = ['file' => [$item->hasUpload() ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png']];

        foreach ($item->fields() as $field => $definition) {
            $rules['details.'.$field] = array_filter([
                $definition['required'] ? 'required' : 'nullable',
                'string',
                $definition['type'] === 'date' ? 'date' : null,
                $definition['type'] === 'textarea' ? 'max:1000' : 'max:255',
            ]);
        }

        $validated = $request->validate($rules);

        $this->service->recordUpload($item, $request->file('file'), $validated['details'] ?? []);

        return response()->json([
            'message' => $item->label().' saved.',
            'data' => new BackgroundCheckResource($check->fresh()->load(['items', 'reviewer'])),
        ]);
    }

    /** Hand the whole set to HR. */
    public function submit(Request $request): JsonResponse
    {
        $check = $this->check($request);

        abort_unless($check->isOpenToEmployee(), 403, 'This verification has already been submitted.');

        if (! $check->load('items')->isReadyToSubmit()) {
            throw ValidationException::withMessages([
                'submit' => 'Please provide every document on the list before submitting.',
            ]);
        }

        $this->service->submit($check);

        return response()->json([
            'message' => 'Thank you — your documents are with our HR team.',
            'data' => new BackgroundCheckResource($check->fresh()->load(['items', 'reviewer'])),
        ]);
    }

    /**
     * Read back a document one has uploaded oneself.
     *
     * Streamed from the private disk like every other identity document, never
     * served by a URL anybody could guess.
     */
    public function document(Request $request, BackgroundCheckItem $item): StreamedResponse
    {
        $check = $this->check($request);

        abort_unless($item->background_check_id === $check->id, 403);
        abort_unless($item->hasUpload(), 404, 'Nothing has been uploaded for this document yet.');

        return $this->service->download($item);
    }

    /** The signed-in employee's own case, or a clear reason there isn't one. */
    protected function check(Request $request): BackgroundCheck
    {
        abort_unless($request->user()->can('bgv.complete-own'), 403);

        $employee = $request->user()->employee;

        abort_unless(
            $employee,
            404,
            'Your login is not linked to an employee record yet, so there is nothing to verify. Ask HR to link them.',
        );

        $check = $employee->backgroundCheck;

        abort_unless(
            $check,
            404,
            'You have not been asked for background verification. There is nothing to do here.',
        );

        return $check;
    }
}
