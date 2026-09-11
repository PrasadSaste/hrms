<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LetterResource;
use App\Models\Letter;
use App\Services\LetterPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Letters over the API.
 *
 * The reason letters are issued through the system at all: somebody who needs
 * their appointment letter three years later fetches it themselves. That is
 * only true if they can do it from the app they actually have open.
 */
class LetterApiController extends Controller
{
    public function __construct(protected LetterPdfService $pdf) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('letters.view-own') || $user->can('letters.view'), 403);

        $canViewAll = $user->can('letters.view');
        $employeeId = $user->employee?->id ?? 0;

        $letters = Letter::with('company')
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $employeeId))
            ->when(
                $canViewAll && $request->integer('employee_id'),
                fn ($q) => $q->where('employee_id', $request->integer('employee_id')),
            )
            ->ofType($request->string('type')->toString() ?: null)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => LetterResource::collection($letters->items()),
            'meta' => [
                'current_page' => $letters->currentPage(),
                'last_page' => $letters->lastPage(),
                'total' => $letters->total(),
            ],
        ]);
    }

    public function show(Letter $letter): JsonResponse
    {
        $this->authorize('view', $letter);

        return response()->json([
            'data' => new LetterResource($letter->load(['company', 'employee'])),
        ]);
    }

    public function download(Letter $letter): Response
    {
        $this->authorize('download', $letter);

        return $this->pdf->make($letter)->download($letter->filename());
    }

    /** What types this account has actually been issued, for a filter. */
    public function types(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('letters.view-own') || $user->can('letters.view'), 403);

        $letters = Letter::query()
            ->when(! $user->can('letters.view'), fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0))
            ->select('type')
            ->distinct()
            ->pluck('type');

        return response()->json([
            'data' => $letters->map(fn (string $type) => [
                'type' => $type,
                'label' => (new Letter(['type' => $type]))->typeLabel(),
            ])->values(),
        ]);
    }
}
