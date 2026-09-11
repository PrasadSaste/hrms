<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\NotificationService;
use App\Services\PayslipPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PayslipController extends Controller
{
    public function __construct(
        protected PayslipPdfService $pdf,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payslip::class);

        $user = $request->user();
        $canViewAll = $user->can('payslips.view-all');

        $payslips = Payslip::query()
            ->with(['employee.department', 'payroll'])
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0)->published())
            ->when($canViewAll && $user->scopedBranchId(), fn ($q, $branchId) => $q
                ->whereHas('employee', fn ($e) => $e->where('branch_id', $user->scopedBranchId())))
            ->when($request->integer('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->integer('year'), fn ($q, $v) => $q->whereYear('period_start', $v))
            ->when($request->integer('month'), fn ($q, $v) => $q->whereMonth('period_start', $v))
            ->when($request->string('payment_status')->toString(), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $v)))
            ->orderByDesc('period_start')
            ->paginate(20)
            ->withQueryString();

        return view('payslips.index', [
            'payslips' => $payslips,
            'canViewAll' => $canViewAll,
            'branches' => Branch::active()->orderBy('name')->get(),
            'employees' => $canViewAll
                ? Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code'])
                : collect(),
            'years' => range((int) date('Y'), (int) date('Y') - 5),
        ]);
    }

    public function show(Payslip $payslip): View
    {
        $this->authorize('view', $payslip);

        $payslip->load([
            'employee.branch', 'employee.department', 'employee.designation',
            'payroll', 'earnings', 'deductions',
        ]);

        return view('payslips.show', [
            'payslip' => $payslip,
            'company' => $this->pdf->companyDetails(),
        ]);
    }

    public function download(Payslip $payslip): Response
    {
        $this->authorize('download', $payslip);

        return $this->pdf->make($payslip)->download($this->pdf->filename($payslip));
    }

    public function stream(Payslip $payslip): Response
    {
        $this->authorize('download', $payslip);

        return $this->pdf->make($payslip)->stream($this->pdf->filename($payslip));
    }

    public function email(Payslip $payslip): RedirectResponse
    {
        $this->authorize('email', $payslip);

        $this->notifications->sendPayslip($payslip);

        return back()->with('success', 'Payslip queued for delivery to '.$payslip->employee->email.'.');
    }

    public function update(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorize('update', $payslip);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'payment_reference' => ['nullable', 'string', 'max:64'],
        ]);

        $payslip->update($validated);

        return back()->with('success', 'Payslip updated.');
    }
}
