<?php

namespace App\Services;

use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Models\Employee;
use App\Models\User;
use App\Support\BackgroundCheckRequirements;
use App\Support\NotificationData;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The background verification process, from the invitation to the day someone
 * is properly onboarded.
 *
 * The rules live here rather than in the controllers because the same steps run
 * from the employee screen, the HR screen and the employee-creation form.
 */
class BackgroundCheckService
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    /**
     * Ask an employee to complete their verification.
     *
     * Re-inviting somebody who already has a case does not wipe what they have
     * already uploaded: it adds any newly requested items and leaves the rest
     * alone.
     *
     * @param  array<int, string>|null  $requirements  defaults to the standard set
     */
    public function invite(
        Employee $employee,
        ?array $requirements = null,
        ?User $invitedBy = null,
        ?string $dueOn = null,
    ): BackgroundCheck {
        $requirements = array_values(array_filter(
            $requirements ?? BackgroundCheckRequirements::defaults(),
            fn (string $key) => BackgroundCheckRequirements::exists($key),
        ));

        $check = DB::transaction(function () use ($employee, $requirements, $invitedBy, $dueOn) {
            $check = BackgroundCheck::firstOrNew(['employee_id' => $employee->id]);

            $check->fill([
                'status' => $check->exists && $check->isVerified()
                    ? $check->status
                    : BackgroundCheck::INVITED,
                'due_on' => $dueOn ?? $check->due_on,
                'invited_at' => $check->invited_at ?? now(),
                'invited_by' => $check->invited_by ?? $invitedBy?->id,
            ])->save();

            foreach ($requirements as $requirement) {
                BackgroundCheckItem::firstOrCreate(
                    ['background_check_id' => $check->id, 'requirement' => $requirement],
                    ['status' => BackgroundCheckItem::PENDING, 'is_required' => true],
                );
            }

            return $check->fresh('items');
        });

        $this->dispatcher->toUser(
            NotificationEvents::BGV_INVITED,
            $employee->user,
            NotificationData::forBackgroundCheck($check),
            ['background_check_id' => $check->id],
        );

        // A new joiner may not have a login yet, so the invitation also goes to
        // the address on their record.
        if (! $employee->user) {
            $this->dispatcher->toAddress(
                NotificationEvents::BGV_INVITED,
                $employee->email,
                NotificationData::forBackgroundCheck($check),
            );
        }

        return $check;
    }

    /**
     * Change what a joiner is asked for after the invitation has gone out.
     *
     * HR ticks the wrong thing sometimes: an experience letter from someone
     * who has never worked before. Anything no longer on the list is removed,
     * together with the file behind it; anything new is added as pending. If
     * something new is added after the employee has already submitted, the
     * case is handed back to them and they are emailed the revised checklist.
     *
     * @param  array<int, string>  $requirements  the complete list that should apply now
     * @return array{check: BackgroundCheck, added: array<int, string>, removed: array<int, string>}
     */
    public function updateRequirements(
        BackgroundCheck $check,
        array $requirements,
        ?string $dueOn = null,
    ): array {
        if ($check->isVerified()) {
            throw new \LogicException('A verified case cannot be changed.');
        }

        $requirements = array_values(array_unique(array_filter(
            $requirements,
            fn (string $key) => BackgroundCheckRequirements::exists($key),
        )));

        $check->loadMissing('items', 'employee.user');
        $current = $check->items->pluck('requirement')->all();
        $added = array_values(array_diff($requirements, $current));
        $removed = array_values(array_diff($current, $requirements));

        $check = DB::transaction(function () use ($check, $added, $removed, $dueOn) {
            foreach ($check->items as $item) {
                if (in_array($item->requirement, $removed, true)) {
                    if ($item->file_path) {
                        Storage::delete($item->file_path);
                    }
                    $item->delete();
                }
            }

            foreach ($added as $requirement) {
                BackgroundCheckItem::create([
                    'background_check_id' => $check->id,
                    'requirement' => $requirement,
                    'status' => BackgroundCheckItem::PENDING,
                    'is_required' => true,
                ]);
            }

            $attributes = ['due_on' => $dueOn];

            // Something new to provide means the employee has work to do again.
            if ($added && $check->isAwaitingReview()) {
                $attributes['status'] = BackgroundCheck::IN_PROGRESS;
            }

            $check->update($attributes);

            return $check->fresh(['items', 'employee.user']);
        });

        if ($added) {
            $this->dispatcher->toUser(
                NotificationEvents::BGV_INVITED,
                $check->employee->user,
                NotificationData::forBackgroundCheck($check),
                ['background_check_id' => $check->id],
            );

            if (! $check->employee->user) {
                $this->dispatcher->toAddress(
                    NotificationEvents::BGV_INVITED,
                    $check->employee->email,
                    NotificationData::forBackgroundCheck($check),
                );
            }
        }

        return ['check' => $check, 'added' => $added, 'removed' => $removed];
    }

    /** Record what an employee has uploaded against one requirement. */
    public function recordUpload(
        BackgroundCheckItem $item,
        ?UploadedFile $file,
        array $details = [],
    ): BackgroundCheckItem {
        return DB::transaction(function () use ($item, $file, $details) {
            if ($file) {
                // Replacing an upload removes the old file rather than leaving
                // an identity document lying around unreferenced.
                if ($item->file_path) {
                    Storage::delete($item->file_path);
                }

                $path = $file->store(
                    'background-checks/'.$item->background_check_id,
                );

                $item->fill([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_at' => now(),
                ]);
            }

            $item->fill([
                'details' => $this->cleanDetails($item, $details),
                // A fresh upload is waiting to be looked at again, and any
                // earlier rejection no longer applies to this file.
                'status' => $item->file_path
                    ? BackgroundCheckItem::UPLOADED
                    : BackgroundCheckItem::PENDING,
                'remarks' => $file ? null : $item->remarks,
            ])->save();

            $check = $item->check;

            if (in_array($check->status, [BackgroundCheck::INVITED], true)) {
                $check->update(['status' => BackgroundCheck::IN_PROGRESS]);
            }

            return $item->fresh();
        });
    }

    /** Hand the whole thing to HR to look at. */
    public function submit(BackgroundCheck $check): BackgroundCheck
    {
        $check->loadMissing('items', 'employee');

        $check->update([
            'status' => BackgroundCheck::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $check = $check->fresh(['items', 'employee']);
        $data = NotificationData::forBackgroundCheck($check);

        $this->dispatcher->toUsers(
            NotificationEvents::BGV_SUBMITTED,
            $this->reviewers(),
            $data,
            ['background_check_id' => $check->id],
        );

        return $check;
    }

    /** Accept one document. */
    public function verifyItem(BackgroundCheckItem $item, User $reviewer, ?string $remarks = null): BackgroundCheckItem
    {
        $item->update([
            'status' => BackgroundCheckItem::VERIFIED,
            'remarks' => $remarks,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $item->fresh();
    }

    /** Send one document back, with a reason the employee will read. */
    public function rejectItem(BackgroundCheckItem $item, User $reviewer, string $remarks): BackgroundCheckItem
    {
        $item->update([
            'status' => BackgroundCheckItem::REJECTED,
            'remarks' => $remarks,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $item->fresh();
    }

    /**
     * Hand the case back to the employee for the items that were not accepted.
     */
    public function requestChanges(BackgroundCheck $check, User $reviewer, ?string $remarks = null): BackgroundCheck
    {
        $check->update([
            'status' => BackgroundCheck::CHANGES_REQUESTED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'review_remarks' => $remarks,
        ]);

        $check = $check->fresh(['items', 'employee.user']);

        $this->dispatcher->toUser(
            NotificationEvents::BGV_CHANGES_REQUESTED,
            $check->employee->user,
            NotificationData::forBackgroundCheck($check),
            ['background_check_id' => $check->id],
        );

        return $check;
    }

    /**
     * Clear the check and take the employee on.
     *
     * Onboarding is the point of the exercise, so it happens here rather than
     * being a second thing somebody has to remember to do.
     */
    public function complete(BackgroundCheck $check, User $reviewer, ?string $remarks = null): BackgroundCheck
    {
        $check->loadMissing('items', 'employee.user');

        return DB::transaction(function () use ($check, $reviewer, $remarks) {
            // Anything still sitting unreviewed is accepted by this decision,
            // so the record does not claim a document was never looked at.
            foreach ($check->items as $item) {
                if ($item->hasUpload() && ! $item->isVerified()) {
                    $this->verifyItem($item, $reviewer);
                }
            }

            $check->update([
                'status' => BackgroundCheck::VERIFIED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_remarks' => $remarks,
                'onboarded_at' => now(),
            ]);

            $check = $check->fresh(['items', 'employee.user']);

            $this->dispatcher->toUser(
                NotificationEvents::BGV_VERIFIED,
                $check->employee->user,
                NotificationData::forBackgroundCheck($check),
                ['background_check_id' => $check->id],
            );

            return $check;
        });
    }

    /** Everyone who should hear that a case is waiting. */
    /**
     * One uploaded document, streamed from the private disk.
     *
     * An identity document is never reachable by URL: it is read through the
     * application so the permission check runs on every single fetch. The
     * caller decides who may see it; this decides where it comes from.
     */
    public function download(BackgroundCheckItem $item): StreamedResponse
    {
        abort_unless(
            $item->file_path && Storage::exists($item->file_path),
            404,
            'The stored file is missing.',
        );

        return Storage::download($item->file_path, $item->file_name);
    }

    public function reviewers(): Collection
    {
        return User::query()
            ->active()
            ->role([Roles::HR_MANAGER, Roles::SUPER_ADMIN])
            ->get()
            ->filter(fn (User $user) => $user->can('bgv.manage'))
            ->values();
    }

    /**
     * Keep only the fields this requirement actually asks for, so nothing
     * unexpected is written into the record.
     */
    protected function cleanDetails(BackgroundCheckItem $item, array $details): array
    {
        $allowed = array_keys($item->fields());

        return collect($details)
            ->only($allowed)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }
}
