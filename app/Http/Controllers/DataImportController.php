<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\DataImport;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\SalaryStructure;
use App\Services\DataImportService;
use App\Support\ImportTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Moving an old HRMS in, one spreadsheet at a time.
 *
 * Upload, look at what the system made of it, then decide. Nothing is written
 * until somebody has seen the answer to "what will this do".
 */
class DataImportController extends Controller
{
    public function __construct(protected DataImportService $imports) {}

    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('data-import.index', [
            'types' => ImportTypes::all(),
            'counts' => $this->existingCounts(),
            'history' => DataImport::with('user')->latest()->take(15)->get(),
        ]);
    }

    /** The column reference and upload form for one type. */
    public function show(Request $request, string $type): View
    {
        $this->authorise($request);
        abort_unless(ImportTypes::exists($type), 404);

        return view('data-import.show', [
            'type' => $type,
            'definition' => ImportTypes::find($type),
            'aliases' => $this->imports->importer($type)->aliases(),
            'componentColumns' => $type === ImportTypes::SALARY_STRUCTURES
                ? $this->imports->salaryComponentColumns()
                : [],
            'history' => DataImport::with('user')->where('type', $type)->latest()->take(5)->get(),
        ]);
    }

    /** The empty file to fill in, with one example row. */
    public function template(Request $request, string $type): Response
    {
        $this->authorise($request);
        abort_unless(ImportTypes::exists($type), 404);

        return response($this->imports->template($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hrms-'.$type.'-template.csv"',
        ]);
    }

    /**
     * Read the file and report on it. Nothing is imported here.
     */
    public function store(Request $request, string $type): RedirectResponse
    {
        $this->authorise($request);
        abort_unless(ImportTypes::exists($type), 404);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,tsv'],
        ], [
            'file.mimes' => 'Upload a CSV file. In Excel or Google Sheets choose File, then '
                .'Save as or Download, and pick "CSV".',
        ]);

        $file = $request->file('file');

        $import = $this->imports->check(
            $type,
            $this->imports->store($file),
            $file->getClientOriginalName(),
            $request->user(),
        );

        return redirect()->route('data-import.review', $import);
    }

    /** What the file holds, and what is wrong with it. */
    public function review(Request $request, DataImport $dataImport): View
    {
        $this->authorise($request);

        return view('data-import.review', [
            'import' => $dataImport->load('user'),
            'definition' => ImportTypes::find($dataImport->type),
            'preview' => $this->imports->preview($dataImport),
        ]);
    }

    /** Write it. */
    public function commit(Request $request, DataImport $dataImport): RedirectResponse
    {
        $this->authorise($request);

        $this->imports->apply($dataImport, $request->boolean('skip_invalid'));

        $import = $dataImport->fresh();

        return redirect()->route('data-import.review', $import)->with('success', sprintf(
            '%s imported: %d added, %d updated%s.',
            $import->typeLabel(),
            $import->rows_created,
            $import->rows_updated,
            $import->rows_skipped > 0 ? ', '.$import->rows_skipped.' skipped' : '',
        ));
    }

    /** The rows that failed, with the reason, ready to fix and upload again. */
    public function errors(Request $request, DataImport $dataImport): Response
    {
        $this->authorise($request);

        return response($this->imports->errorReport($dataImport), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$dataImport->type.'-rows-to-fix.csv"',
        ]);
    }

    /** What is already in the system, so nobody imports on top of a full table by accident. */
    protected function existingCounts(): array
    {
        return [
            ImportTypes::BRANCHES => Branch::count(),
            ImportTypes::DEPARTMENTS => Department::count(),
            ImportTypes::DESIGNATIONS => Designation::count(),
            ImportTypes::EMPLOYEES => Employee::count(),
            ImportTypes::LEAVE_BALANCES => LeaveAllocation::count(),
            ImportTypes::SALARY_STRUCTURES => SalaryStructure::count(),
            ImportTypes::ATTENDANCE => Attendance::count(),
        ];
    }

    protected function authorise(Request $request): void
    {
        abort_unless($request->user()->can('data.import'), 403);
    }
}
