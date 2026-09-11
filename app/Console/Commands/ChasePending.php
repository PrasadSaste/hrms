<?php

namespace App\Console\Commands;

use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\ReminderService;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chase what is sitting still.
 *
 * Leave waiting on a manager who is away, an attendance correction nobody
 * opened, a joiner invited to send documents who never did. All of it was
 * already visible on a screen; nothing went looking for it.
 *
 * One message per person listing everything, rather than one per request: a
 * manager with six waiting does not need six emails, and a single list is
 * easier to act on than six things to remember.
 */
class ChasePending extends AutomationCommand
{
    protected $signature = 'hrms:chase-pending
        {--after= : Chase anything sitting longer than this many days.}
        {--date= : Reason from this date rather than today (Y-m-d).}
        {--dry-run : Show who would be chased without sending anything.}';

    protected $description = 'Remind people about approvals and documents that have been sitting';

    public function __construct(
        protected ReminderService $reminders,
        protected NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'reminders.chase-pending';
    }

    protected function work(): string
    {
        $after = (int) ($this->option('after') ?: ReminderService::DEFAULT_AFTER_DAYS);
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $leave = $this->reminders->pendingLeave($after, $asOf);
        $corrections = $this->reminders->pendingRegularizations($after, $asOf);
        $documents = $this->reminders->unsubmittedChecks($after, $asOf);

        $approvers = $this->merge($leave, $corrections);

        if ($approvers->isEmpty() && $documents->isEmpty()) {
            return 'Nothing has been waiting more than '.$after.' days.';
        }

        $chased = 0;

        foreach ($approvers as $row) {
            $this->line('  '.$row['user']->name.' — '.$row['leave']->count().' leave, '
                .$row['corrections']->count().' corrections');

            if (! $dryRun && $this->tellApprover($row, $asOf)) {
                $chased++;
            }
        }

        foreach ($documents as $check) {
            $this->line('  '.$check->employee->full_name.' — documents outstanding');

            if (! $dryRun && $this->tellJoiner($check, $asOf)) {
                $chased++;
            }
        }

        $this->affected = $dryRun ? 0 : $chased;

        return $dryRun
            ? 'Would chase '.($approvers->count() + $documents->count()).' '
                .str('person')->plural($approvers->count() + $documents->count()).'.'
            : sprintf(
                'Chased %d %s: %d waiting on an approver, %d with documents outstanding.',
                $chased,
                str('person')->plural($chased),
                $approvers->count(),
                $documents->count(),
            );
    }

    /**
     * The two kinds of approval, brought together per person.
     *
     * @return Collection<int, array{user: User, leave: Collection, corrections: Collection}>
     */
    protected function merge(Collection $leave, Collection $corrections): Collection
    {
        $rows = [];

        foreach ($leave as $row) {
            $rows[$row['user']->id] = [
                'user' => $row['user'],
                'leave' => $row['requests'],
                'corrections' => collect(),
            ];
        }

        foreach ($corrections as $row) {
            $id = $row['user']->id;
            $rows[$id] ??= ['user' => $row['user'], 'leave' => collect(), 'corrections' => collect()];
            $rows[$id]['corrections'] = $row['requests'];
        }

        return collect($rows)->values();
    }

    protected function tellApprover(array $row, Carbon $asOf): bool
    {
        $items = collect();
        $oldest = 0;

        foreach ($row['leave'] as $request) {
            $waiting = (int) $request->applied_on->diffInDays($asOf);
            $oldest = max($oldest, $waiting);
            $items->push(sprintf(
                '- **Leave** — %s, %s, waiting %d %s',
                $request->employee?->full_name ?? 'Somebody',
                $request->leaveType?->name ?? 'leave',
                $waiting,
                str('day')->plural($waiting),
            ));
        }

        foreach ($row['corrections'] as $correction) {
            $waiting = (int) $correction->created_at->diffInDays($asOf);
            $oldest = max($oldest, $waiting);
            $items->push(sprintf(
                '- **Attendance correction** — %s for %s, waiting %d %s',
                $correction->employee?->full_name ?? 'Somebody',
                $correction->date->format('d M Y'),
                $waiting,
                str('day')->plural($waiting),
            ));
        }

        $total = $row['leave']->count() + $row['corrections']->count();

        $data = [
            'first_name' => str($row['user']->name)->before(' ')->toString() ?: $row['user']->name,
            'total' => (string) $total,
            'leave_count' => (string) $row['leave']->count(),
            'regularization_count' => (string) $row['corrections']->count(),
            'oldest_days' => (string) $oldest,
            'items' => $items->implode("\n"),
            'url' => route('leave.index'),
        ];

        // toUser writes the in-app notification as well as the email, so
        // calling database() beside it would give somebody the same reminder
        // twice in their bell.
        $this->dispatcher->toUser(NotificationEvents::REMINDER_WAITING, $row['user'], $data);

        return true;
    }

    protected function tellJoiner(BackgroundCheck $check, Carbon $asOf): bool
    {
        $employee = $check->employee;
        $waiting = (int) $check->invited_at->diffInDays($asOf);

        $outstanding = $check->items()
            ->where('status', BackgroundCheckItem::PENDING)
            ->get()
            ->map(fn (BackgroundCheckItem $item) => '- '.$item->label())
            ->implode("\n");

        $data = [
            'first_name' => $employee->first_name,
            'employee_name' => $employee->full_name,
            'invited_on' => $check->invited_at->format('d M Y'),
            'waiting_days' => (string) $waiting,
            'due_on' => $check->due_on?->format('d M Y') ?? '—',
            'outstanding' => $outstanding !== '' ? $outstanding : '- Everything we asked for.',
            'url' => route('my-verification.edit'),
        ];

        if (! $employee->user) {
            return false;
        }

        $this->dispatcher->toUser(NotificationEvents::REMINDER_DOCUMENTS, $employee->user, $data);

        return true;
    }
}
