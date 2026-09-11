<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Support\NotificationData;
use App\Support\NotificationEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handing company property out and getting it back.
 *
 * Two rules do all the work. An asset is with at most one person at a time, so
 * issuing one that is already out is refused rather than quietly opening a
 * second assignment and losing track of the first. And the asset's status is
 * written from its assignments here, never typed in on a form, so "issued" and
 * "who has it" cannot end up disagreeing.
 */
class AssetService
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    /**
     * Hand an asset to somebody.
     *
     * @param  array{issued_on?: string|Carbon|null, condition_out?: string, issue_remarks?: string|null}  $details
     *
     * @throws ValidationException when the asset is not free to give
     */
    public function issue(Asset $asset, Employee $employee, ?User $by = null, array $details = []): AssetAssignment
    {
        $asset->loadMissing('currentAssignment.employee');

        if ($asset->currentAssignment) {
            $holder = $asset->currentAssignment->employee;

            throw ValidationException::withMessages([
                'asset_id' => $asset->name.' is already with '
                    .($holder?->full_name ?? 'somebody else')
                    .'. Take it back first, then issue it again.',
            ]);
        }

        if (! $asset->isAvailable()) {
            throw ValidationException::withMessages([
                'asset_id' => $asset->name.' is marked "'.$asset->statusLabel().'" and cannot be issued.',
            ]);
        }

        return DB::transaction(function () use ($asset, $employee, $by, $details) {
            $assignment = $asset->assignments()->create([
                'employee_id' => $employee->id,
                'issued_on' => $details['issued_on'] ?? Carbon::today(),
                'issued_by' => $by?->id,
                'condition_out' => $details['condition_out'] ?? $asset->condition ?? 'good',
                'issue_remarks' => $details['issue_remarks'] ?? null,
            ]);

            $asset->update(['status' => Asset::ISSUED]);

            $this->tell(NotificationEvents::ASSET_ISSUED, $employee, $asset, $assignment);

            return $assignment;
        });
    }

    /**
     * Take one back.
     *
     * The condition it comes back in is recorded on the assignment and copied
     * onto the asset, because the next person to be given it should see the
     * state it is actually in rather than the state it was bought in.
     *
     * @param  array{returned_on?: string|Carbon|null, condition_in?: string, return_remarks?: string|null, status?: string}  $details
     */
    public function return(Asset $asset, ?User $by = null, array $details = []): AssetAssignment
    {
        $assignment = $asset->currentAssignment()->first();

        if (! $assignment) {
            throw ValidationException::withMessages([
                'asset_id' => $asset->name.' is not with anybody, so there is nothing to take back.',
            ]);
        }

        return DB::transaction(function () use ($asset, $assignment, $by, $details) {
            $condition = $details['condition_in'] ?? $assignment->condition_out;

            $assignment->update([
                'returned_on' => $details['returned_on'] ?? Carbon::today(),
                'received_by' => $by?->id,
                'condition_in' => $condition,
                'return_remarks' => $details['return_remarks'] ?? null,
            ]);

            // Back on the shelf unless it came back broken, in which case
            // saying so is the whole point of asking.
            $asset->update([
                'condition' => $condition,
                'status' => $details['status'] ?? Asset::IN_STOCK,
            ]);

            $this->tell(NotificationEvents::ASSET_RETURNED, $assignment->employee, $asset, $assignment);

            return $assignment->fresh();
        });
    }

    /**
     * Move an asset from one person to another without it touching the shelf.
     *
     * A handover is two facts, not one: the first person stopped being
     * responsible on a date and the second started. Doing it as a return and
     * an issue keeps both.
     */
    public function transfer(Asset $asset, Employee $to, ?User $by = null, array $details = []): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $to, $by, $details) {
            if ($asset->currentAssignment()->exists()) {
                $this->return($asset, $by, [
                    'returned_on' => $details['issued_on'] ?? Carbon::today(),
                    'condition_in' => $details['condition_out'] ?? null,
                    'return_remarks' => 'Handed over to '.$to->full_name.'.',
                ]);
            }

            return $this->issue($asset->fresh(), $to, $by, $details);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | What is still out
    |--------------------------------------------------------------------------
    */

    /**
     * What this person is still holding.
     *
     * @return Collection<int, AssetAssignment>
     */
    public function heldBy(Employee $employee): Collection
    {
        return $employee->heldAssets()->with('asset')->get();
    }

    /**
     * What a leaver has to hand back before they go.
     *
     * Only the kinds the company actually wants returned: chasing somebody for
     * a pair of safety boots on their last day is how a clearance list stops
     * being taken seriously.
     *
     * @return Collection<int, AssetAssignment>
     */
    public function outstandingFor(Employee $employee): Collection
    {
        return $this->heldBy($employee)
            ->filter(fn (AssetAssignment $a) => $a->asset && $a->asset->isReturnable())
            ->values();
    }

    /** Whether this person can be relieved without anything going missing. */
    public function isCleared(Employee $employee): bool
    {
        return $this->outstandingFor($employee)->isEmpty();
    }

    /**
     * Everybody on their way out who still has something.
     *
     * @return Collection<int, Employee>
     */
    public function leaversHoldingAssets(): Collection
    {
        return Employee::query()
            ->whereNotNull('date_of_exit')
            ->whereHas('heldAssets')
            ->with(['heldAssets.asset', 'branch'])
            ->get()
            ->filter(fn (Employee $e) => $this->outstandingFor($e)->isNotEmpty())
            ->values();
    }

    /** How the register breaks down, for the chips above it. */
    public function counts(?User $user = null): array
    {
        $rows = Asset::query()
            ->visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => (int) $rows->sum(),
            ...collect(Asset::STATUSES)->map(fn ($label, $key) => (int) ($rows[$key] ?? 0))->all(),
        ];
    }

    /** Tell the person, through the catalogue like everything else. */
    protected function tell(string $event, ?Employee $employee, Asset $asset, AssetAssignment $assignment): void
    {
        if (! $employee?->user) {
            return;
        }

        $this->dispatcher->toUser(
            $event,
            $employee->user,
            NotificationData::forAssetAssignment($assignment),
            ['url' => route('my-assets.index')],
        );
    }
}
