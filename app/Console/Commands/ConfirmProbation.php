<?php

namespace App\Console\Commands;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Services\GeofenceService;
use App\Services\NotificationDispatcher;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;

/**
 * Move people off probation once their probation has ended.
 *
 * Two things were being missed. Where a confirmation date is recorded, the
 * leave rate corrects itself on the day — `Employee::isOnProbation()` reads the
 * date — but the employment status stays "probation" in every list, filter and
 * report until a person edits the record, and nobody is prompted to issue the
 * confirmation letter. Where no confirmation date was recorded at all, the
 * status is the only thing that decides, so somebody keeps earning the lower
 * probation rate indefinitely.
 *
 * Only the status is written. A confirmation letter says something a company
 * means, so it stays a deliberate act — this only makes sure somebody is asked.
 */
class ConfirmProbation extends AutomationCommand
{
    protected $signature = 'hrms:confirm-probation
        {--date= : Reason from this date rather than today (Y-m-d).}
        {--dry-run : List who would be confirmed without writing anything.}';

    protected $description = 'Move people whose probation has ended off probation';

    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected GeofenceService $recipients,
    ) {
        parent::__construct();
    }

    protected function automationKey(): string
    {
        return 'people.confirm-probation';
    }

    protected function work(): string
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $due = Employee::query()
            ->with(['designation', 'department', 'branch', 'manager', 'user'])
            ->where('employment_status', EmploymentStatus::Probation)
            ->whereNotNull('date_of_confirmation')
            ->whereDate('date_of_confirmation', '<=', $asOf)
            ->get();

        if ($due->isEmpty()) {
            return 'Nobody reached the end of probation.';
        }

        foreach ($due as $employee) {
            $this->line('  '.$employee->employee_code.'  '.$employee->full_name
                .'  confirmed '.$employee->date_of_confirmation->format('d M Y'));

            if ($dryRun) {
                continue;
            }

            $employee->update(['employment_status' => EmploymentStatus::Permanent]);
            $this->tell($employee);
        }

        $this->affected = $dryRun ? 0 : $due->count();

        return $dryRun
            ? $due->count().' would be confirmed.'
            : 'Confirmed '.$due->count().' '.str('person')->plural($due->count()).'.';
    }

    /** HR is told, because a confirmation letter is still theirs to issue. */
    protected function tell(Employee $employee): void
    {
        $data = [
            'employee_name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation?->name ?? '—',
            'department' => $employee->department?->name ?? '—',
            'branch' => $employee->branch?->name ?? '—',
            'date_of_joining' => $employee->date_of_joining->format('d M Y'),
            'date_of_confirmation' => $employee->date_of_confirmation->format('d M Y'),
            'manager' => $employee->manager?->full_name ?? '—',
            'url' => route('employees.show', $employee),
        ];

        foreach ($this->recipients->recipients($employee) as $recipient) {
            $this->dispatcher->database(NotificationEvents::PROBATION_CONFIRMED, $recipient['user'], $data);
            $this->dispatcher->toAddress(NotificationEvents::PROBATION_CONFIRMED, $recipient['email'], $data);
        }
    }
}
