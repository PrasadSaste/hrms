<?php

namespace App\Http\Controllers;

use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What an employee is holding.
 *
 * Read-only on purpose: somebody signing off their own laptop as returned is
 * exactly the thing a register exists to prevent. Handing back goes through
 * whoever receives it.
 */
class MyAssetController extends Controller
{
    public function __construct(protected AssetService $assets) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('assets.view-own'), 403);

        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return view('assets.mine', [
            'held' => $this->assets->heldBy($employee),
            'history' => $employee->assetAssignments()
                ->with(['asset', 'issuer', 'receiver'])
                ->whereNotNull('returned_on')
                ->get(),
        ]);
    }
}
