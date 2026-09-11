<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Announcement::class);

        $canManage = $request->user()->can('announcements.manage');

        $announcements = Announcement::query()
            ->with(['branch', 'department', 'author'])
            ->when(! $canManage, fn ($q) => $q->published()->visibleTo($request->user()->employee))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('announcements.index', [
            'announcements' => $announcements,
            'canManage' => $canManage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcements.create', array_merge($this->options(), [
            'announcement' => new Announcement(['status' => 'draft']),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $announcement = Announcement::create(
            $this->validated($request) + ['created_by' => $request->user()->id]
        );

        $notified = 0;
        if ($announcement->status === 'published') {
            $notified = $this->notifications->broadcastAnnouncement($announcement);
        }

        return redirect()->route('announcements.show', $announcement)
            ->with('success', 'Announcement saved.'.($notified ? " {$notified} employee(s) notified." : ''));
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        $announcement->load(['branch', 'department', 'author']);

        // Record the read receipt for the current user.
        $announcement->readers()->syncWithoutDetaching([
            $request->user()->id => ['read_at' => now()],
        ]);

        return view('announcements.show', [
            'announcement' => $announcement,
            'canManage' => $request->user()->can('announcements.manage'),
            'readCount' => $announcement->readers()->count(),
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        $this->authorize('update', $announcement);

        return view('announcements.edit', array_merge($this->options(), [
            'announcement' => $announcement,
        ]));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $wasPublished = $announcement->status === 'published';
        $announcement->update($this->validated($request));

        $notified = 0;
        if (! $wasPublished && $announcement->status === 'published') {
            $notified = $this->notifications->broadcastAnnouncement($announcement);
        }

        return redirect()->route('announcements.show', $announcement)
            ->with('success', 'Announcement updated.'.($notified ? " {$notified} employee(s) notified." : ''));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'is_pinned' => ['nullable', 'boolean'],
            'notify_by_email' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['notify_by_email'] = $request->boolean('notify_by_email');

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function options(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
        ];
    }
}
