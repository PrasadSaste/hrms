<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceSession;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckItem;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\Payslip;
use App\Models\User;

/**
 * Turns a model into the placeholder values its templates expect.
 *
 * Everything here is formatted for reading, because the template author works
 * in words rather than in objects: dates are already written out, money is
 * already carrying its symbol, and an absent value is left out so the renderer
 * can show its own dash for it.
 */
final class NotificationData
{
    /** @return array<string, string> */
    public static function forLeave(LeaveRequest $request): array
    {
        $request->loadMissing(['employee', 'leaveType', 'approver']);

        return [
            'first_name' => $request->employee->first_name,
            'employee_name' => $request->employee->full_name,
            'employee_code' => $request->employee->employee_code,
            'leave_type' => $request->leaveType->name,
            'period' => $request->periodLabel(),
            'days' => self::number($request->total_days),
            'reason' => $request->reason,
            'applied_on' => $request->applied_on?->format('d M Y, h:i A'),
            'contact' => $request->contact_during_leave,
            'status' => strtolower($request->status->label()),
            'approver' => $request->approver?->name,
            'actioned_on' => $request->actioned_at?->format('d M Y, h:i A'),
            'remarks' => $request->status->value === 'cancelled'
                ? $request->cancel_reason
                : $request->approver_remarks,
            'reference' => $request->reference,
            'url' => route('leave.show', $request),
        ];
    }

    /** @return array<string, string> */
    public static function forRegularization(AttendanceRegularization $regularization): array
    {
        $regularization->loadMissing(['employee', 'reviewer']);

        return [
            'first_name' => $regularization->employee->first_name,
            'employee_name' => $regularization->employee->full_name,
            'date' => $regularization->date->format('d M Y'),
            'requested_check_in' => $regularization->requested_check_in?->format('h:i A'),
            'requested_check_out' => $regularization->requested_check_out?->format('h:i A'),
            'reason' => $regularization->reason,
            'status' => strtolower($regularization->status->label()),
            'reviewer' => $regularization->reviewer?->name,
            'remarks' => $regularization->review_remarks,
            'url' => route('attendance.regularizations.index'),
        ];
    }

    /**
     * One punch made further from the branch than it allows.
     *
     * @return array<string, string>
     */
    public static function forGeofenceBreach(AttendanceSession $session, string $punch): array
    {
        $session->loadMissing(['employee.branch', 'employee.department']);

        $employee = $session->employee;
        $at = $punch === 'check_out' ? $session->ended_at : $session->started_at;
        $branch = $employee->branch;

        return [
            'first_name' => $employee->first_name,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'department' => $employee->department?->name,
            'branch' => $branch?->name,
            'punch' => $punch === 'check_out' ? 'check-out' : 'check-in',
            'punch_time' => $at?->format('d M Y, h:i A'),
            'distance' => Geo::describeDistance($session->{$punch.'_distance_metres'}),
            'allowed_radius' => $branch ? Geo::describeDistance($branch->geofenceRadius()) : null,
            'location' => $session->{$punch.'_location'},
            'map_url' => $session->mapUrl($punch),
            'url' => route('attendance.location-alerts', ['date' => $session->started_at->toDateString()]),
        ];
    }

    /**
     * A letter that has been issued.
     *
     * @return array<string, string>
     */
    public static function forLetter(Letter $letter): array
    {
        $letter->loadMissing('employee');

        return [
            'first_name' => $letter->employee->first_name,
            'employee_name' => $letter->employee->full_name,
            'employee_code' => $letter->employee->employee_code,
            'letter_type' => strtolower($letter->typeLabel()),
            'subject' => $letter->subject,
            'reference' => $letter->reference,
            'issued_on' => $letter->issued_on->format('d M Y'),
            'url' => route('my-letters.index'),
        ];
    }

    /** @return array<string, string> */
    public static function forPayslip(Payslip $payslip): array
    {
        $payslip->loadMissing('employee');

        return [
            'first_name' => $payslip->employee->first_name,
            'employee_name' => $payslip->employee->full_name,
            'period' => $payslip->periodLabel(),
            'slip_number' => $payslip->slip_number,
            'period_start' => $payslip->period_start->format('d M Y'),
            'period_end' => $payslip->period_end->format('d M Y'),
            'paid_days' => self::number($payslip->paid_days),
            'working_days' => self::number($payslip->working_days),
            'gross' => Money::withSymbol($payslip->gross_earnings, $payslip->currency),
            'deductions' => Money::withSymbol($payslip->total_deductions, $payslip->currency),
            'net_pay' => Money::withSymbol($payslip->net_pay, $payslip->currency),
            'payment_date' => $payslip->payment_date?->format('d M Y'),
            'payment_reference' => $payslip->payment_reference,
            'url' => route('payslips.show', $payslip),
        ];
    }

    /** @return array<string, string> */
    public static function forAnnouncement(Announcement $announcement): array
    {
        $announcement->loadMissing('author');

        return [
            'title' => $announcement->title,
            'body' => $announcement->body,
            'author' => $announcement->author?->name ?? 'HR',
            'published_on' => $announcement->published_at?->format('d M Y'),
            'url' => route('announcements.show', $announcement),
        ];
    }

    /**
     * @return array<string, string>
     *
     * The checklist is written out as markdown here rather than in the
     * template, because a template cannot loop over a list.
     */
    public static function forBackgroundCheck(BackgroundCheck $check): array
    {
        $check->loadMissing(['employee.designation', 'employee.branch', 'items', 'reviewer']);

        $items = $check->items;
        $outstanding = $check->itemsNeedingWork();

        return [
            'first_name' => $check->employee->first_name,
            'employee_name' => $check->employee->full_name,
            'employee_code' => $check->employee->employee_code,
            'designation' => $check->employee->designation?->name,
            'branch' => $check->employee->branch?->name,
            'date_of_joining' => $check->employee->date_of_joining?->format('d M Y'),
            'checklist' => self::checklist($items),
            'outstanding' => self::checklist($outstanding),
            'required_count' => (string) $check->requiredItems()->count(),
            'uploaded_count' => (string) $items->filter(fn (BackgroundCheckItem $i) => $i->hasUpload())->count(),
            'outstanding_count' => (string) $outstanding->count(),
            'due_on' => $check->due_on?->format('d M Y'),
            'submitted_on' => $check->submitted_at?->format('d M Y, h:i A'),
            'reviewed_on' => $check->reviewed_at?->format('d M Y, h:i A'),
            'reviewer' => $check->reviewer?->name,
            'remarks' => $check->review_remarks,
            'status' => $check->statusLabel(),
            // Two audiences, two destinations: the employee is sent to their
            // own checklist, HR to the review screen.
            'url' => route('my-verification.edit'),
            'review_url' => route('background-checks.show', $check),
        ];
    }

    /** One markdown line per document, saying where it has got to. */
    private static function checklist(iterable $items): string
    {
        $lines = [];

        foreach ($items as $item) {
            $note = $item->status === BackgroundCheckItem::REJECTED && $item->remarks
                ? ' — '.$item->remarks
                : '';

            $lines[] = '- **'.$item->label().'** — '.strtolower($item->statusLabel()).$note;
        }

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    /**
     * An asset handed over or handed back.
     *
     * The condition is in here because it is the one detail somebody argues
     * about later, and a message that stated it at the time settles it.
     */
    public static function forAssetAssignment(AssetAssignment $assignment): array
    {
        $assignment->loadMissing(['asset', 'employee']);

        $asset = $assignment->asset;

        return [
            'first_name' => $assignment->employee?->first_name,
            'employee_name' => $assignment->employee?->full_name,
            'asset_name' => $asset?->name,
            'asset_tag' => $asset?->asset_tag,
            'asset_type' => $asset?->typeLabel(),
            'serial_number' => $asset?->identifier(),
            'issued_on' => $assignment->issued_on?->format('d M Y'),
            'returned_on' => $assignment->returned_on?->format('d M Y'),
            'condition_out' => Asset::CONDITIONS[$assignment->condition_out] ?? $assignment->condition_out,
            'condition_in' => Asset::CONDITIONS[$assignment->condition_in] ?? $assignment->condition_in,
            'remarks' => $assignment->returned_on ? $assignment->return_remarks : $assignment->issue_remarks,
        ];
    }

    public static function forWelcome(Employee $employee, ?string $password = null): array
    {
        $employee->loadMissing(['designation', 'department', 'branch', 'manager']);

        return [
            'first_name' => $employee->first_name,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation?->name,
            'department' => $employee->department?->name,
            'branch' => $employee->branch?->name,
            'date_of_joining' => $employee->date_of_joining?->format('d M Y'),
            'manager' => $employee->manager?->full_name,
            'email' => $employee->email,
            'temporary_password' => $password,
            'login_url' => route('login'),
        ];
    }

    /** @return array<string, string> */
    public static function forAccount(User $user, ?string $password = null): array
    {
        return [
            'user_name' => $user->name,
            'email' => $user->email,
            'temporary_password' => $password,
            'login_url' => route('login'),
        ];
    }

    /** Trim a trailing .0 so "3 days" does not read as "3.0 days". */
    private static function number(float|int|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }
}
