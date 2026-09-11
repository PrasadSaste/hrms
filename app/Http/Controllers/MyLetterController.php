<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Services\LetterPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * An employee's own letters.
 *
 * The reason for issuing letters through the system at all: somebody who needs
 * their appointment letter three years later fetches it themselves instead of
 * asking HR to search an inbox.
 */
class MyLetterController extends Controller
{
    public function __construct(protected LetterPdfService $pdf) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('letters.view-own'), 403);

        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return view('letters.mine', [
            'letters' => Letter::where('employee_id', $employee->id)
                ->with('company')
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function download(Request $request, Letter $letter): Response
    {
        $this->authorize('download', $letter);

        return $this->pdf->make($letter)->download($letter->filename());
    }
}
