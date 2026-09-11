<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PunchRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\BreakReasons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AttendanceApiController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /** Today's punch state for the authenticated employee. */
    public function today(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $attendance = $this->attendance->todayFor($employee);

        return response()->json([
            'date' => Carbon::today()->toDateString(),
            'checked_in' => $attendance?->check_in !== null,
            'checked_out' => $attendance?->check_out !== null,
            // Lets a mobile client ask for permission before offering the button.
            'location_required' => AttendanceService::locationRequired(),
            'attendance' => $attendance ? new AttendanceResource($attendance) : null,
        ]);
    }

    public function checkIn(PunchRequest $request): JsonResponse
    {
        $this->authorize('punch', Attendance::class);

        $attendance = $this->attendance->checkIn(
            $this->employee($request),
            null,
            'api',
            $request->ip(),
            $request->location(),
        );

        return response()->json([
            'message' => 'Punched in at '.$attendance->sessions->last()->started_at->format('h:i A').'.',
            'attendance' => new AttendanceResource($attendance),
        ], 201);
    }

    /** Start a break, saying what it is for. */
    public function startBreak(Request $request): JsonResponse
    {
        $this->authorize('punch', Attendance::class);

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(BreakReasons::keys())],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance = $this->attendance->startBreak(
            $this->employee($request),
            $validated['reason'],
            $validated['comment'] ?? null,
            null,
            'api',
        );

        return response()->json([
            'message' => BreakReasons::label($validated['reason']).' break started.',
            'attendance' => new AttendanceResource($attendance),
        ], 201);
    }

    /** End the break that is running. */
    public function endBreak(Request $request): JsonResponse
    {
        $this->authorize('punch', Attendance::class);

        $attendance = $this->attendance->endBreak($this->employee($request));

        return response()->json([
            'message' => 'Break ended after '.$attendance->breaks->last()->duration_minutes.' minutes.',
            'attendance' => new AttendanceResource($attendance),
        ]);
    }

    /** What the reasons are, so a mobile app can draw the picker. */
    public function breakReasons(): JsonResponse
    {
        return response()->json([
            'data' => collect(BreakReasons::all())
                ->map(fn (array $reason, string $key) => [
                    'key' => $key,
                    'label' => $reason['label'],
                    'description' => $reason['description'],
                ])
                ->values(),
        ]);
    }

    public function checkOut(PunchRequest $request): JsonResponse
    {
        $this->authorize('punch', Attendance::class);

        $attendance = $this->attendance->checkOut(
            $this->employee($request),
            null,
            'api',
            $request->ip(),
            $request->location(),
        );

        return response()->json([
            'message' => 'Checked out after '.$attendance->durationLabel().'.',
            'attendance' => new AttendanceResource($attendance),
        ]);
    }

    /** Attendance history for the authenticated employee. */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $records = Attendance::where('employee_id', $employee->id)
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($validated['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 31);

        return response()->json([
            'data' => AttendanceResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /** Monthly summary with per-day breakdown. */
    public function summary(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $year = $request->integer('year') ?: (int) date('Y');
        $month = $request->integer('month') ?: (int) date('n');

        $summary = $this->attendance->monthlySummary($employee, $year, $month);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'totals' => $summary['totals'],
            'days' => collect($summary['days'])->map(fn (array $day) => [
                'date' => $day['date'],
                'kind' => $day['kind'],
                'status' => $day['status']?->value,
                'status_label' => $day['status']?->label(),
                'check_in' => $day['attendance']?->check_in?->format('H:i'),
                'check_out' => $day['attendance']?->check_out?->format('H:i'),
                'worked_minutes' => $day['attendance']?->worked_minutes ?? 0,
            ])->values(),
        ]);
    }

    protected function employee(Request $request)
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 404, 'No employee record is linked to your account.');

        return $employee;
    }
}
