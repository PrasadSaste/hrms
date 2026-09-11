<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Support\Geo;
use App\Support\NotificationData;
use App\Support\NotificationEvents;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How far a punch was made from the branch it belongs to.
 *
 * Attendance already records where somebody was standing; a branch now records
 * where it is. This turns those two into a distance, decides whether it is
 * further than the branch allows, and works out who should hear about it.
 *
 * Nothing here refuses a punch. Somebody at a client's office, on a site visit,
 * or indoors with a poor fix has a perfectly good reason to be somewhere else,
 * and refusing to record their day would lose real attendance to catch the rare
 * case of somebody punching in from home. The punch is recorded, the distance
 * is kept beside it, and a person decides.
 */
class GeofenceService
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    /** Whether distances are being checked at all. */
    public function enabled(): bool
    {
        return (bool) Setting::get('attendance_geofence_enabled', true);
    }

    /** The organisation-wide radius, used by any branch without its own. */
    public function defaultRadius(): int
    {
        return max(1, (int) Setting::get('attendance_geofence_radius', 200));
    }

    /**
     * Measure one punch against its branch.
     *
     * Returns the columns to store on the session: the distance, and the
     * verdict frozen at the moment of the punch. An empty array means there was
     * nothing to measure — the check is off, the branch has no coordinates, or
     * the device reported no position — and the punch is recorded as it always
     * was.
     *
     * @return array<string, mixed>
     */
    public function columnsFor(string $prefix, ?Employee $employee, ?PunchLocation $location): array
    {
        $branch = $employee?->branch;

        if (! $this->enabled() || ! $location?->hasCoordinates() || ! $branch?->hasCoordinates()) {
            return [];
        }

        $metres = (int) round(Geo::distanceInMetres(
            $branch->latitude,
            $branch->longitude,
            $location->latitude,
            $location->longitude,
        ));

        return [
            $prefix.'_distance_metres' => $metres,
            $prefix.'_outside_geofence' => $metres > $branch->geofenceRadius(),
        ];
    }

    /**
     * Tell the nominated people about a punch made too far away.
     *
     * Called after the session is saved, so the alert describes something that
     * happened rather than something about to. The employee is not told: they
     * know where they were standing, and an accusing email about a client visit
     * they were sent on is worse than useless.
     */
    public function alert(AttendanceSession $session, string $punch): void
    {
        if (! $session->{$punch.'_outside_geofence'}) {
            return;
        }

        $session->loadMissing(['employee.branch.manager.user', 'employee.manager.user', 'employee.department']);

        $key = NotificationEvents::GEOFENCE_BREACH;
        $data = NotificationData::forGeofenceBreach($session, $punch);
        $payload = ['attendance_session_id' => $session->id, 'punch' => $punch];

        foreach ($this->recipients($session->employee) as $recipient) {
            $this->dispatcher->database($key, $recipient['user'], $data, $payload);
            $this->dispatcher->toAddress($key, $recipient['email'], $data);
        }
    }

    /**
     * Email the day's summary to the same people.
     *
     * Sent whether or not there were any: a report that only arrives on a bad
     * day teaches people that no report means nothing happened, which is also
     * what a stopped scheduler looks like.
     *
     * @param  array<string, string>  $data  the placeholder values for the report
     * @return int how many messages were handed to the mailer
     */
    public function sendDailyReport(array $data): int
    {
        $sent = 0;

        foreach ($this->recipients() as $recipient) {
            if ($this->dispatcher->toAddress(NotificationEvents::GEOFENCE_REPORT, $recipient['email'], $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Who hears about a punch away from the branch.
     *
     * The addresses an administrator nominated, optionally the employee's own
     * managers, and — if nobody has been nominated at all — every active HR
     * manager and administrator, so an alert is never quietly sent nowhere.
     *
     * @return Collection<int, array{email: string, user: ?User}>
     */
    public function recipients(?Employee $employee = null): Collection
    {
        $recipients = collect();

        foreach ($this->nominatedAddresses() as $email) {
            $recipients->push(['email' => $email, 'user' => null]);
        }

        if ($employee && (bool) Setting::get('attendance_geofence_alert_managers', false)) {
            foreach ([$employee->manager?->user, $employee->branch?->manager?->user] as $user) {
                if ($user?->isActive()) {
                    $recipients->push(['email' => $user->email, 'user' => $user]);
                }
            }
        }

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->active()
                ->role([Roles::HR_MANAGER, Roles::SUPER_ADMIN])
                ->get()
                ->map(fn (User $user) => ['email' => $user->email, 'user' => $user]);
        }

        // An address that belongs to a login is worth resolving: that person
        // then gets the alert in their bell menu as well as their inbox.
        $users = User::query()
            ->whereIn('email', $recipients->pluck('email')->filter()->all())
            ->get()
            ->keyBy(fn (User $user) => strtolower($user->email));

        return $recipients
            ->map(fn (array $recipient) => [
                'email' => $recipient['email'],
                'user' => $recipient['user'] ?? $users->get(strtolower($recipient['email'])),
            ])
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values();
    }

    /** The addresses saved on the settings screen. */
    public function nominatedAddresses(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) Setting::get('attendance_geofence_alert_recipients', '')) ?: [])
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every punch flagged on one day, as flat rows ready to render.
     *
     * A session can contribute two rows: somebody who arrives at the office and
     * then punches out from a client's premises has one punch inside the radius
     * and one outside, and only the second is worth reading about.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function flaggedPunches(Carbon $date, ?User $viewer = null): Collection
    {
        $sessions = AttendanceSession::query()
            ->outsideGeofence()
            ->whereDate('started_at', $date->toDateString())
            ->when($viewer, fn ($q) => $q->whereHas(
                'employee',
                fn ($employee) => $employee->visibleTo($viewer),
            ))
            ->with(['employee.branch', 'employee.department'])
            ->orderBy('started_at')
            ->get();

        return $sessions
            ->flatMap(function (AttendanceSession $session) {
                $rows = [];

                foreach (['check_in', 'check_out'] as $punch) {
                    if (! $session->{$punch.'_outside_geofence'}) {
                        continue;
                    }

                    $at = $punch === 'check_out' ? $session->ended_at : $session->started_at;
                    $metres = $session->{$punch.'_distance_metres'};

                    $rows[] = [
                        'session' => $session,
                        'employee' => $session->employee,
                        'branch' => $session->employee?->branch,
                        'punch' => $punch,
                        'punch_label' => $punch === 'check_out' ? 'Check-out' : 'Check-in',
                        'at' => $at,
                        'distance_metres' => $metres,
                        'distance' => Geo::describeDistance($metres),
                        'allowed_radius' => $session->employee?->branch?->geofenceRadius(),
                        'location' => $session->{$punch.'_location'},
                        'accuracy' => $session->{$punch.'_accuracy'},
                        'map_url' => $session->mapUrl($punch),
                    ];
                }

                return $rows;
            })
            ->sortByDesc('distance_metres')
            ->values();
    }

    /** Branches that have not been placed on the map, so are never checked. */
    public function branchesWithoutCoordinates(): Collection
    {
        return Branch::query()
            ->active()
            ->whereNull('latitude')
            ->orderBy('name')
            ->get();
    }
}
