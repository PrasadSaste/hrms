<?php

namespace App\Http\Controllers;

use App\Enums\PayrollStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Payroll;
use App\Services\Bank\BankFileService;
use App\Services\NotificationService;
use App\Services\PayrollService;
use App\Support\BankFormats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payroll,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payroll::class);

        $runs = Payroll::query()
            ->with(['company', 'branch', 'generatedBy', 'approvedBy'])
            ->withCount('payslips')
            ->forBranch($request->user()->scopedBranchId())
            ->when($request->integer('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->integer('year'), fn ($q, $v) => $q->where('year', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(15)
            ->withQueryString();

        return view('payroll.index', [
            'runs' => $runs,
            'companies' => Company::active()->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'statuses' => PayrollStatus::options(),
            'years' => range((int) date('Y') + 1, (int) date('Y') - 5),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Payroll::class);

        $lastMonth = Carbon::today()->subMonthNoOverflow();

        return view('payroll.create', [
            'companies' => Company::active()->orderBy('name')->get(),
            'defaultCompany' => Company::default(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'defaultMonth' => (int) $lastMonth->format('n'),
            'defaultYear' => (int) $lastMonth->format('Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Payroll::class);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'generate_now' => ['nullable', 'boolean'],
        ]);

        $run = $this->payroll->createRun($validated, $request->user());

        if ($request->boolean('generate_now', true)) {
            $result = $this->payroll->generate($run);

            return redirect()->route('payroll.show', $run)
                ->with('success', $result['generated'].' payslip(s) generated.')
                ->with('skipped', $result['skipped']);
        }

        return redirect()->route('payroll.show', $run)
            ->with('success', 'Payroll run created. Generate payslips when you are ready.');
    }

    public function show(Payroll $payroll): View
    {
        $this->authorize('view', $payroll);

        $payroll->load(['branch', 'generatedBy', 'approvedBy']);

        return view('payroll.show', [
            'payroll' => $payroll,
            'payslips' => $payroll->payslips()
                ->with(['employee.department', 'employee.designation'])
                ->join('employees', 'employees.id', '=', 'payslips.employee_id')
                ->orderBy('employees.employee_code')
                ->select('payslips.*')
                ->paginate(15),
        ]);
    }

    public function generate(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorize('generate', $payroll);

        $result = $this->payroll->generate($payroll);

        return back()
            ->with('success', $result['generated'].' payslip(s) generated for '.$payroll->periodLabel().'.')
            ->with('skipped', $result['skipped']);
    }

    public function submit(Payroll $payroll): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $this->payroll->submitForApproval($payroll);

        return back()->with('success', 'Payroll run submitted for approval.');
    }

    public function approve(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorize('approve', $payroll);

        $this->payroll->approve($payroll, $request->user());

        return back()->with('success', 'Payroll approved. Payslips are now visible to employees.');
    }

    public function markPaid(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorize('markPaid', $payroll);

        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:64'],
            'payment_date' => ['nullable', 'date'],
        ]);

        $this->payroll->markPaid(
            $payroll,
            $validated['payment_reference'] ?? null,
            isset($validated['payment_date']) ? Carbon::parse($validated['payment_date']) : null,
        );

        return back()->with('success', 'Payroll marked as paid.');
    }

    /** Email every published payslip in the run. */
    public function emailPayslips(Payroll $payroll): RedirectResponse
    {
        $this->authorize('view', $payroll);
        abort_unless(request()->user()->can('payslips.email'), 403);

        $result = $this->notifications->sendPayrollPayslips($payroll);

        $message = $result['sent'].' payslip(s) queued for delivery.';
        if ($result['failed'] > 0) {
            $message .= ' '.$result['failed'].' failed and were logged.';
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payroll->update($validated);

        return back()->with('success', 'Payroll run updated.');
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        $this->authorize('delete', $payroll);

        $payroll->delete();

        return redirect()->route('payroll.index')->with('success', 'Payroll run deleted.');
    }

    /**
     * The screen that stands between an approved run and the bank.
     *
     * Everything wrong with the run is listed here, before a file exists —
     * a missing account number, an IFSC that is not one, two people sharing
     * an account. The bank would tell you the same thing, days later, after
     * the salary date.
     */
    public function bankFile(Request $request, Payroll $payroll, BankFileService $bank): View
    {
        $this->authorize('view', $payroll);

        $payroll->loadMissing('company');

        // Choosing a layout reloads the screen rather than only changing what
        // the next click downloads: the point of the column list underneath is
        // that it is the layout about to be written, and a stale one would be
        // worse than none.
        $formatKey = $request->string('format')->toString();
        $formatKey = BankFormats::has($formatKey) ? $formatKey : $bank->formatFor($payroll);

        return view('payroll.bank-file', [
            'payroll' => $payroll,
            'formats' => BankFormats::all(),
            'format' => BankFormats::get($formatKey),
            'formatKey' => $formatKey,
            'problems' => $bank->problems($payroll),
            'payable' => $bank->payable($payroll),
            'excluded' => $bank->excluded($payroll),
            'total' => $bank->total($payroll),
            'valueDate' => $payroll->payment_date ?? now(),
            'canDownload' => request()->user()->can('bankFile', $payroll),
        ]);
    }

    /** The file itself. */
    public function downloadBankFile(Request $request, Payroll $payroll, BankFileService $bank): StreamedResponse|RedirectResponse
    {
        $this->authorize('bankFile', $payroll);

        $validated = $request->validate([
            'format' => ['required', 'string', Rule::in(BankFormats::keys())],
            'value_date' => ['nullable', 'date'],
        ]);

        $problems = $bank->problems($payroll);

        // The check is refused, not warned about: a file with a bad row in it
        // is not worth producing, because the bank rejects the whole upload
        // and the salary date does not move for anybody.
        if ($problems !== []) {
            return redirect()
                ->route('payroll.bank-file', $payroll)
                ->with('error', 'The file was not produced: '.count($problems).' '.Str::plural('problem', $problems).' to fix first.');
        }

        // Remembered against the company, because next month is the same bank.
        if ($payroll->company && $payroll->company->bank_file_format !== $validated['format']) {
            $payroll->company->update(['bank_file_format' => $validated['format']]);
        }

        $contents = $bank->contents(
            $payroll,
            $validated['format'],
            isset($validated['value_date']) ? Carbon::parse($validated['value_date']) : null,
        );

        $filename = $bank->filename($payroll, $validated['format']);

        return response()->streamDownload(
            fn () => print ($contents),
            $filename,
            ['Content-Type' => 'text/plain'],
        );
    }

    /** Bank-transfer style register as CSV. */
    public function export(Payroll $payroll): StreamedResponse
    {
        $this->authorize('view', $payroll);

        $payslips = $this->payroll->registerFor($payroll);

        $filename = 'payroll-register-'.$payroll->year.'-'.str_pad((string) $payroll->month, 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use ($payslips) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Slip Number', 'Employee Code', 'Employee', 'Department', 'Designation',
                'Bank Account', 'IFSC', 'Working Days', 'Paid Days', 'LOP Days',
                'Basic', 'Gross Earnings', 'Deductions', 'Net Pay', 'Payment Status',
            ]);

            foreach ($payslips as $slip) {
                fputcsv($handle, [
                    $slip->slip_number,
                    $slip->employee?->employee_code,
                    $slip->employee?->full_name,
                    $slip->employee?->department?->name,
                    $slip->employee?->designation?->name,
                    $slip->employee?->bank_account_number,
                    $slip->employee?->bank_ifsc,
                    $slip->working_days,
                    $slip->paid_days,
                    $slip->lop_days,
                    number_format($slip->basic_salary, 2, '.', ''),
                    number_format($slip->gross_earnings, 2, '.', ''),
                    number_format($slip->total_deductions, 2, '.', ''),
                    number_format($slip->net_pay, 2, '.', ''),
                    ucfirst($slip->payment_status),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
