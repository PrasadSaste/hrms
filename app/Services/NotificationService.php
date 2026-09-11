<?php

namespace App\Services;

use App\Mail\TemplatedLetterMail;
use App\Mail\TemplatedPayslipMail;
use App\Models\Announcement;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\User;
use App\Support\NotificationData;
use App\Support\NotificationEvents;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * One place that decides who gets told about what.
 *
 * What each message says, and whether it goes out by email, in the bell menu or
 * not at all, is settled by the notification console; this class only works out
 * who should hear about an event and hands the dispatcher the values its
 * template needs.
 */
class NotificationService
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function notifyLeaveSubmitted(LeaveRequest $request): void
    {
        $request->loadMissing(['employee.manager.user', 'employee.branch.manager.user', 'leaveType']);

        $this->dispatcher->toUsers(
            NotificationEvents::LEAVE_SUBMITTED,
            $this->approversFor($request->employee),
            NotificationData::forLeave($request),
            ['leave_request_id' => $request->id],
        );
    }

    public function notifyLeaveActioned(LeaveRequest $request): void
    {
        $request->loadMissing(['employee.user', 'leaveType', 'approver']);

        $key = $request->status->value === 'rejected'
            ? NotificationEvents::LEAVE_REJECTED
            : NotificationEvents::LEAVE_APPROVED;

        $data = NotificationData::forLeave($request);

        $this->dispatcher->database($key, $request->employee->user, $data, [
            'leave_request_id' => $request->id,
        ]);
        $this->dispatcher->toAddress($key, $request->employee->email, $data);
    }

    /**
     * Tell the approvers that a request they had agreed to is off.
     *
     * The employee cancelled it themselves, so they are the one person who does
     * not need telling.
     */
    public function notifyLeaveCancelled(LeaveRequest $request): void
    {
        $request->loadMissing(['employee.manager.user', 'employee.branch.manager.user', 'leaveType']);

        $this->dispatcher->toUsers(
            NotificationEvents::LEAVE_CANCELLED,
            $this->approversFor($request->employee),
            NotificationData::forLeave($request),
            ['leave_request_id' => $request->id],
        );
    }

    public function notifyRegularizationSubmitted(AttendanceRegularization $regularization): void
    {
        $regularization->loadMissing('employee.manager.user', 'employee.branch.manager.user');

        $this->dispatcher->toUsers(
            NotificationEvents::REGULARIZATION_SUBMITTED,
            $this->approversFor($regularization->employee),
            NotificationData::forRegularization($regularization),
            ['regularization_id' => $regularization->id],
        );
    }

    public function notifyRegularizationActioned(AttendanceRegularization $regularization): void
    {
        $regularization->loadMissing('employee.user', 'reviewer');

        $key = NotificationEvents::REGULARIZATION_ACTIONED;
        $data = NotificationData::forRegularization($regularization);

        $this->dispatcher->database($key, $regularization->employee->user, $data, [
            'regularization_id' => $regularization->id,
        ]);
        $this->dispatcher->toAddress($key, $regularization->employee->email, $data);
    }

    /**
     * Send an employee a letter that has been issued to them.
     *
     * The attachment is rendered when the queued message is sent rather than
     * now, so a letter reprinted a year later still comes out on the same
     * letterhead it was issued under.
     */
    public function sendLetter(Letter $letter): void
    {
        $letter->loadMissing(['employee.user', 'company']);

        $key = NotificationEvents::LETTER_ISSUED;
        $data = NotificationData::forLetter($letter);

        $this->dispatcher->database($key, $letter->employee->user, $data, ['letter_id' => $letter->id]);

        $sent = $this->dispatcher->toAddress(
            $key,
            $letter->employee->email,
            $data,
            new TemplatedLetterMail($letter, $key, $data),
        );

        if ($sent) {
            $letter->forceFill(['emailed_at' => now()])->save();
        }
    }

    /** Tell one employee that their payslip is ready, with the PDF attached. */
    public function sendPayslip(Payslip $payslip, bool $email = true): void
    {
        $payslip->loadMissing('employee.user');

        $key = NotificationEvents::PAYSLIP_PUBLISHED;
        $data = NotificationData::forPayslip($payslip);

        $this->dispatcher->database($key, $payslip->employee->user, $data, [
            'payslip_id' => $payslip->id,
        ]);

        // emailed_at is stamped by RecordPayslipDelivery once the message has
        // actually left, because queueing it is not the same as sending it.
        if ($email) {
            $this->dispatcher->toAddress(
                $key,
                $payslip->employee->email,
                $data,
                new TemplatedPayslipMail($payslip, $key, $data),
            );
        }
    }

    /**
     * Email every published payslip in a run.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendPayrollPayslips(Payroll $payroll): array
    {
        $sent = 0;
        $failed = 0;

        $payroll->payslips()->published()->with('employee.user')->chunkById(50, function (Collection $slips) use (&$sent, &$failed) {
            foreach ($slips as $payslip) {
                try {
                    $this->sendPayslip($payslip);
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Failed to email payslip', [
                        'payslip_id' => $payslip->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Publish an announcement to its audience, optionally by email. */
    public function broadcastAnnouncement(Announcement $announcement): int
    {
        $recipients = $this->announcementAudience($announcement);
        $key = NotificationEvents::ANNOUNCEMENT_PUBLISHED;
        $data = NotificationData::forAnnouncement($announcement);
        $payload = ['announcement_id' => $announcement->id];

        foreach ($recipients as $user) {
            $this->dispatcher->database($key, $user, $data, $payload);

            // Email is per-announcement as well as per-event: an author can
            // post something quietly without switching the channel off for
            // everyone else.
            if ($announcement->notify_by_email) {
                $this->dispatcher->toAddress($key, $user->email, $data + ['recipient_name' => $user->name]);
            }
        }

        return $recipients->count();
    }

    /** Welcome a new employee and, when they have one, give them their login. */
    public function sendWelcome(Employee $employee, ?string $password = null): bool
    {
        return $this->dispatcher->toAddress(
            NotificationEvents::EMPLOYEE_WELCOME,
            $employee->email,
            NotificationData::forWelcome($employee, $password) + ['recipient_name' => $employee->full_name],
        );
    }

    /** Send account credentials, either newly created or reset by an administrator. */
    public function sendCredentials(User $user, string $password, bool $reset = false): bool
    {
        $key = $reset
            ? NotificationEvents::ACCOUNT_PASSWORD_RESET_BY_ADMIN
            : NotificationEvents::ACCOUNT_CREDENTIALS;

        return $this->dispatcher->toAddress(
            $key,
            $user->email,
            NotificationData::forAccount($user, $password) + ['recipient_name' => $user->name],
        );
    }

    /** Users who may approve requests for an employee. */
    public function approversFor(Employee $employee): Collection
    {
        $approvers = collect();

        if ($manager = $employee->manager?->user) {
            $approvers->push($manager);
        }

        if ($branchManager = $employee->branch?->manager?->user) {
            $approvers->push($branchManager);
        }

        if ($approvers->isEmpty()) {
            $approvers = User::query()
                ->active()
                ->role([Roles::HR_MANAGER, Roles::SUPER_ADMIN])
                ->get();
        }

        return $approvers
            ->filter(fn (User $user) => $user->isActive() && $user->id !== $employee->user_id)
            ->unique('id')
            ->values();
    }

    protected function announcementAudience(Announcement $announcement): Collection
    {
        return User::query()
            ->active()
            ->whereHas('employee', function ($q) use ($announcement) {
                if ($announcement->branch_id) {
                    $q->where('branch_id', $announcement->branch_id);
                }
                if ($announcement->department_id) {
                    $q->where('department_id', $announcement->department_id);
                }
                $q->where('status', 'active');
            })
            ->get();
    }
}
