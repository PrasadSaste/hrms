<?php

namespace App\Http\Controllers;

use App\Models\BackgroundCheckItem;
use App\Services\BackgroundCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The employee's own side of background verification: a checklist to work
 * through, one document at a time, and a button to hand it over when done.
 */
class MyBackgroundCheckController extends Controller
{
    public function __construct(protected BackgroundCheckService $service) {}

    public function edit(Request $request): View
    {
        $check = $this->check($request);

        return view('background-checks.mine', [
            'check' => $check->load(['items', 'reviewer']),
        ]);
    }

    /** Upload or replace one document, with the details asked for beside it. */
    public function upload(Request $request, BackgroundCheckItem $item): RedirectResponse
    {
        $check = $this->check($request);

        abort_unless($item->background_check_id === $check->id, 403);
        abort_unless($check->isOpenToEmployee(), 403, 'This verification has already been submitted.');

        $rules = [
            // Required only the first time: a later save may just be a
            // correction to the typed details.
            'file' => [$item->hasUpload() ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];

        foreach ($item->fields() as $field => $definition) {
            $rules['details.'.$field] = array_filter([
                $definition['required'] ? 'required' : 'nullable',
                'string',
                $definition['type'] === 'date' ? 'date' : null,
                $definition['type'] === 'textarea' ? 'max:1000' : 'max:255',
            ]);
        }

        $validated = $request->validate($rules, [], $this->fieldNames($item));

        $this->service->recordUpload($item, $request->file('file'), $validated['details'] ?? []);

        return back()->with('success', $item->label().' saved.');
    }

    /** Hand the whole set to HR. */
    public function submit(Request $request): RedirectResponse
    {
        $check = $this->check($request);

        abort_unless($check->isOpenToEmployee(), 403);

        if (! $check->load('items')->isReadyToSubmit()) {
            throw ValidationException::withMessages([
                'submit' => 'Please provide every document on the list before submitting.',
            ]);
        }

        $this->service->submit($check);

        return back()->with('success', 'Thank you — your documents are with our HR team.');
    }

    /** The signed-in employee's own case, or a clear reason there isn't one. */
    protected function check(Request $request)
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

    /** Friendly names in validation messages, taken from the requirement. */
    protected function fieldNames(BackgroundCheckItem $item): array
    {
        $names = ['file' => 'document'];

        foreach ($item->fields() as $field => $definition) {
            $names['details.'.$field] = strtolower($definition['label']);
        }

        return $names;
    }
}
