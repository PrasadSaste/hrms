<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Holiday;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryApiController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    /** Colleague directory, limited to what the caller may see. */
    public function employees(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->visibleTo($request->user())
            ->active()
            ->onRoll()
            ->with(['branch', 'department', 'designation'])
            ->search($request->string('search')->toString())
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('first_name')
            ->paginate($request->integer('per_page') ?: 25);

        return response()->json([
            'data' => EmployeeResource::collection($employees->items()),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function holidays(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: (int) date('Y');
        $branchId = $request->user()->employee?->branch_id;

        return response()->json([
            'year' => $year,
            'data' => Holiday::forBranch($branchId)->inYear($year)->orderBy('date')->get()
                ->map(fn (Holiday $h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date->toDateString(),
                    'day' => $h->date->format('l'),
                    'type' => $h->type,
                    'description' => $h->description,
                ]),
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $announcements = Announcement::published()
            ->visibleTo($request->user()->employee)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page') ?: 10);

        return response()->json([
            'data' => collect($announcements->items())->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'excerpt' => $a->excerpt(),
                'is_pinned' => $a->is_pinned,
                'published_at' => $a->published_at?->toIso8601String(),
            ]),
            'meta' => ['total' => $announcements->total()],
        ]);
    }

    /** Compact dashboard payload for a mobile home screen. */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->dashboard->forUser($user);

        return response()->json([
            'today' => $data['today']->toDateString(),
            'self' => isset($data['self']) ? [
                'checked_in' => $data['self']['today_attendance']?->check_in !== null,
                'checked_out' => $data['self']['today_attendance']?->check_out !== null,
                'check_in_at' => $data['self']['today_attendance']?->check_in?->format('H:i'),
                'check_out_at' => $data['self']['today_attendance']?->check_out?->format('H:i'),
                'month_totals' => $data['self']['month_totals'],
                'pending_leave_count' => $data['self']['pending_requests']->count(),
                'leave_balance' => $data['self']['leave_balance']->map(fn ($r) => [
                    'name' => $r['leave_type']->name,
                    'remaining' => $r['remaining'],
                    'entitled' => $r['entitled'],
                ])->values(),
                'latest_payslip' => $data['self']['latest_payslip'] ? [
                    'id' => $data['self']['latest_payslip']->id,
                    'period' => $data['self']['latest_payslip']->periodLabel(),
                    'net_pay' => $data['self']['latest_payslip']->net_pay,
                ] : null,
            ] : null,
            'org' => $data['org'] ?? null,
            'holidays' => $data['holidays']->map(fn ($h) => [
                'name' => $h->name,
                'date' => $h->date->toDateString(),
            ]),
            'announcements' => $data['announcements']->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt(20),
            ]),
            'birthdays' => $data['birthdays']->map(fn ($b) => [
                'name' => $b['employee']->full_name,
                'date' => $b['date']->toDateString(),
            ]),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'data' => collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'meta' => ['total' => $notifications->total()],
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }
}
