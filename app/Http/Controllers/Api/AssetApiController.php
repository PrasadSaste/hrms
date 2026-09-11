<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What somebody is holding, over the API.
 *
 * Read-only, exactly as the web screen is: somebody marking their own laptop
 * returned is the thing a register exists to prevent, and an API is not a way
 * around that.
 */
class AssetApiController extends Controller
{
    public function __construct(protected AssetService $assets) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('assets.view-own'), 403);

        $employee = $user->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return response()->json([
            'data' => $this->assets->heldBy($employee)->map(fn (AssetAssignment $a) => $this->row($a))->values(),
            'meta' => [
                'held' => $this->assets->heldBy($employee)->count(),
                // What they would have to hand back if they left tomorrow.
                'to_return_on_exit' => $this->assets->outstandingFor($employee)->count(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('assets.view-own'), 403);

        $employee = $user->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return response()->json([
            'data' => $employee->assetAssignments()
                ->with('asset')
                ->whereNotNull('returned_on')
                ->get()
                ->map(fn (AssetAssignment $a) => $this->row($a))
                ->values(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function row(AssetAssignment $assignment): array
    {
        $asset = $assignment->asset;

        return [
            'id' => $assignment->id,
            'asset_id' => $asset?->id,
            'name' => $asset?->name,
            'tag' => $asset?->asset_tag,
            'type' => $asset?->type,
            'type_label' => $asset?->typeLabel(),
            'identifier' => $asset?->identifier(),
            'make' => $asset?->make,
            'model' => $asset?->model,
            'issued_on' => $assignment->issued_on?->toDateString(),
            'returned_on' => $assignment->returned_on?->toDateString(),
            'held_days' => $assignment->heldDays(),
            'condition_out' => $assignment->condition_out,
            'condition_in' => $assignment->condition_in,
            'remarks' => $assignment->returned_on ? $assignment->return_remarks : $assignment->issue_remarks,
            'must_return' => (bool) $asset?->isReturnable(),
        ];
    }
}
