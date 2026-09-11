<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Http\Requests\EmployeeRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\BackgroundCheckService;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\NotificationService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employees,
        protected AttendanceService $attendance,
        protected LeaveService $leave,
        protected NotificationService $notifications,
        protected BackgroundCheckService $backgroundChecks,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredQuery($request)
            ->with(['branch', 'department', 'designation', 'user'])
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', array_merge($this->filterOptions(), [
            'employees' => $employees,
            'stats' => $this->headcountStats($request),
        ]));
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create', array_merge($this->formOptions(), [
            'employee' => new Employee([
                'status' => 'active',
                'employment_type' => EmploymentType::FullTime,
                'employment_status' => EmploymentStatus::Probation,
                'date_of_joining' => Carbon::today(),
                'notice_period_days' => 30,
                'country' => 'India',
            ]),
            'suggestedCode' => $this->employees->nextEmployeeCode(),
        ]));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $data = $request->employeeData();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        $result = $this->employees->create(
            $data,
            $request->boolean('create_account', true),
            $request->input('role', Roles::EMPLOYEE),
        );

        if ($request->boolean('send_welcome_email', true)) {
            $this->notifications->sendWelcome($result['employee'], $result['password']);
        }

        // The verification invitation carries the checklist, so it is a second
        // email rather than a paragraph bolted onto the welcome.
        if ($request->boolean('request_bgv') && $request->user()->can('bgv.manage')) {
            $this->backgroundChecks->invite(
                $result['employee'],
                $request->input('bgv_requirements'),
                $request->user(),
                $request->input('bgv_due_on') ?: null,
            );
        }

        return redirect()->route('employees.show', $result['employee'])
            ->with('success', 'Employee '.$result['employee']->full_name.' was added with code '.$result['employee']->employee_code.'.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorize('view', $employee);

        $employee->load([
            'company', 'branch', 'department', 'designation', 'shift', 'manager', 'user.roles',
            'subordinates.designation', 'documents.uploader',
        ]);

        $month = Carbon::createFromDate(
            $request->integer('year') ?: (int) date('Y'),
            $request->integer('month') ?: (int) date('n'),
            1,
        );

        return view('employees.show', [
            'employee' => $employee,
            'attendanceSummary' => $this->attendance->monthlySummary($employee, (int) $month->format('Y'), (int) $month->format('n')),
            'month' => $month,
            'leaveBalance' => $this->leave->balanceSummary($employee),
            'recentLeave' => $employee->leaveRequests()->with('leaveType')->latest('start_date')->take(5)->get(),
            'heldAssets' => $employee->heldAssets()->with('asset')->get(),
            'returnedAssets' => $employee->assetAssignments()->with('asset')->whereNotNull('returned_on')->take(10)->get(),
            'salaryStructure' => $employee->salaryStructureOn(),
            'payslips' => $employee->payslips()->orderByDesc('period_start')->take(6)->get(),
            'canViewSalary' => $request->user()->can('viewSalary', $employee),
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', array_merge($this->formOptions($employee), [
            'employee' => $employee,
        ]));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $data = $request->employeeData();

        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        $this->employees->update($employee, $data);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee record updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->user?->update(['status' => 'inactive']);
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee record was archived.');
    }

    /** Provision a login for an employee who does not have one. */
    public function provisionAccount(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', Roles::all())],
        ]);

        $result = $this->employees->provisionAccount($employee, $validated['role']);

        if ($result['password']) {
            $this->notifications->sendWelcome($employee->fresh(), $result['password']);

            return back()->with('success', 'Login created and credentials emailed to '.$employee->email.'.');
        }

        return back()->with('info', 'This employee already has a login.');
    }

    public function offboard(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'date_of_exit' => ['required', 'date', 'after_or_equal:'.$employee->date_of_joining->toDateString()],
            'employment_status' => ['required', 'in:resigned,terminated,retired'],
            'exit_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->employees->offboard($employee, $validated);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee has been offboarded and their login disabled.');
    }

    /** CSV export of the current filter selection. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredQuery($request)
            ->with(['branch', 'department', 'designation', 'manager'])
            ->orderBy('employee_code')
            ->get();

        $filename = 'employees-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($employees) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee Code', 'First Name', 'Last Name', 'Email', 'Phone',
                'Branch', 'Department', 'Designation', 'Reporting To',
                'Employment Type', 'Employment Status', 'Date of Joining', 'Date of Exit', 'Status',
            ]);

            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->employee_code,
                    $employee->first_name,
                    $employee->last_name,
                    $employee->email,
                    $employee->phone,
                    $employee->branch?->name,
                    $employee->department?->name,
                    $employee->designation?->name,
                    $employee->manager?->full_name,
                    $employee->employment_type->label(),
                    $employee->employment_status->label(),
                    $employee->date_of_joining?->toDateString(),
                    $employee->date_of_exit?->toDateString(),
                    ucfirst($employee->status),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ------------------------------------------------------------- internals

    protected function filteredQuery(Request $request)
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->search($request->string('search')->toString())
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('designation_id'), fn ($q, $id) => $q->where('designation_id', $id))
            ->when($request->string('employment_type')->toString(), fn ($q, $v) => $q->where('employment_type', $v))
            ->when($request->string('employment_status')->toString(), fn ($q, $v) => $q->where('employment_status', $v))
            ->when(
                $request->string('status')->toString(),
                fn ($q, $v) => $q->where('status', $v),
                fn ($q) => $q->where('status', 'active'),
            );
    }

    protected function headcountStats(Request $request): array
    {
        $base = fn () => Employee::query()->visibleTo($request->user());

        return [
            'total' => $base()->where('status', 'active')->count(),
            'on_probation' => $base()->where('employment_status', EmploymentStatus::Probation->value)->count(),
            'notice_period' => $base()->where('employment_status', EmploymentStatus::NoticePeriod->value)->count(),
            'joined_this_month' => $base()
                ->whereYear('date_of_joining', date('Y'))
                ->whereMonth('date_of_joining', date('n'))
                ->count(),
        ];
    }

    protected function filterOptions(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'designations' => Designation::active()->orderBy('name')->get(),
        ];
    }

    protected function formOptions(?Employee $employee = null): array
    {
        return array_merge($this->filterOptions(), [
            'companies' => Company::active()->orderBy('name')->get(),
            'shifts' => Shift::active()->orderBy('name')->get(),
            'managers' => Employee::active()
                ->when($employee, fn ($q) => $q->whereKeyNot($employee->id))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'roles' => Roles::labels(),
        ]);
    }
}
