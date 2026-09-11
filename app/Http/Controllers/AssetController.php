<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AssetService;
use App\Support\AssetTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The company's asset register.
 *
 * Issuing and taking back are here rather than on their own controller
 * because they are the same screen's buttons; the rules for both live in
 * {@see AssetService}, which is what the API and any future importer call too.
 */
class AssetController extends Controller
{
    public function __construct(protected AssetService $assets) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $query = Asset::query()
            ->visibleTo($request->user())
            ->with(['branch', 'company', 'currentAssignment.employee']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($branchId = $request->integer('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        return view('assets.index', [
            'assets' => $query->orderBy('asset_tag')->paginate(25)->withQueryString(),
            'counts' => $this->assets->counts($request->user()),
            'types' => AssetTypes::options(),
            'statuses' => Asset::STATUSES,
            'branches' => Branch::active()->orderBy('name')->get(),
            'filters' => [
                'status' => $status, 'type' => $type,
                'branch_id' => $branchId, 'q' => $search,
            ],
            'canManage' => $request->user()->can('assets.manage'),
            'canAssign' => $request->user()->can('assets.assign'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        return view('assets.form', $this->formData(new Asset(['type' => AssetTypes::LAPTOP])));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        $asset = Asset::create($this->validated($request));

        return redirect()->route('assets.show', $asset)->with('success', $asset->name.' added to the register.');
    }

    public function show(Request $request, Asset $asset): View
    {
        abort_unless($request->user()->can('assets.view'), 403);

        return view('assets.show', [
            'asset' => $asset->load(['branch', 'company', 'assignments.employee', 'assignments.issuer', 'assignments.receiver']),
            'current' => $asset->currentAssignment()->with('employee')->first(),
            'employees' => $this->assignableEmployees($request),
            'conditions' => Asset::CONDITIONS,
            'statuses' => Asset::STATUSES,
            'canManage' => $request->user()->can('assets.manage'),
            'canAssign' => $request->user()->can('assets.assign'),
        ]);
    }

    public function edit(Request $request, Asset $asset): View
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        return view('assets.form', $this->formData($asset));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        $asset->update($this->validated($request, $asset));

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        // Soft-deleted, so the history of who held it does not vanish with it.
        abort_if($asset->isIssued(), 422, 'Take this back from its holder before retiring it.');

        $asset->delete();

        return redirect()->route('assets.index')->with('success', $asset->name.' removed from the register.');
    }

    /*
    |--------------------------------------------------------------------------
    | Handing it over and taking it back
    |--------------------------------------------------------------------------
    */

    public function issue(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->can('assets.assign'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'issued_on' => ['required', 'date'],
            'condition_out' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
            'issue_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $this->assets->issue($asset, $employee, $request->user(), $validated);

        return back()->with('success', $asset->name.' issued to '.$employee->full_name.'.');
    }

    public function return(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->can('assets.assign'), 403);

        $validated = $request->validate([
            'returned_on' => ['required', 'date'],
            'condition_in' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
            'return_remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in([Asset::IN_STOCK, Asset::IN_REPAIR, Asset::LOST, Asset::RETIRED])],
        ]);

        $this->assets->return($asset, $request->user(), $validated);

        return back()->with('success', $asset->name.' is back on the register.');
    }

    /*
    |--------------------------------------------------------------------------
    | Shared bits
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Asset $asset = null): array
    {
        $type = $request->string('type')->toString();

        $rules = [
            'asset_tag' => ['required', 'string', 'max:60', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'type' => ['required', Rule::in(AssetTypes::keys())],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'purchased_on' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'warranty_expires_on' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        // Whatever this kind of thing asks for beside the common fields.
        foreach (AssetTypes::fieldsFor($type) as $field => $definition) {
            $rules['details.'.$field] = array_filter([
                $definition['required'] ? 'required' : 'nullable',
                'string',
                $definition['type'] === 'date' ? 'date' : null,
                'max:255',
            ]);
        }

        $validated = $request->validate($rules);

        // A status is never posted from the form: it is decided by whether
        // the thing is with somebody. A new asset starts on the shelf.
        $validated['status'] = $asset?->status ?? Asset::IN_STOCK;
        $validated['details'] = array_filter($validated['details'] ?? [], fn ($v) => $v !== null && $v !== '');

        return $validated;
    }

    /** @return array<string, mixed> */
    protected function formData(Asset $asset): array
    {
        return [
            'asset' => $asset,
            'types' => AssetTypes::all(),
            'conditions' => Asset::CONDITIONS,
            'companies' => Company::orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
        ];
    }

    /** Who this asset could be given to, within what the user may see. */
    protected function assignableEmployees(Request $request)
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->whereNull('date_of_exit')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code']);
    }
}
