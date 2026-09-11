<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'company_id', 'employee_code',
        'first_name', 'last_name', 'email', 'personal_email', 'phone', 'alternate_phone',
        'gender', 'date_of_birth', 'marital_status', 'blood_group', 'nationality', 'photo_path',
        'address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'branch_id', 'department_id', 'designation_id', 'shift_id', 'reporting_to',
        'employment_type', 'employment_status', 'date_of_joining', 'date_of_confirmation',
        'date_of_exit', 'exit_reason', 'notice_period_days', 'overtime_eligible',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_branch',
        'pan_number', 'national_id', 'pf_number', 'esi_number', 'uan_number',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'date_of_joining' => 'date:Y-m-d',
            'date_of_confirmation' => 'date:Y-m-d',
            'date_of_exit' => 'date:Y-m-d',
            'employment_type' => EmploymentType::class,
            'employment_status' => EmploymentStatus::class,
            'notice_period_days' => 'integer',
            'overtime_eligible' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_to');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_to');
    }

    /**
     * Everybody has an employer.
     *
     * An employee with no company would be quietly left out of every payroll
     * run, so one is filled in on creation rather than allowed to be missing.
     */
    protected static function booted(): void
    {
        static::creating(function (self $employee) {
            $employee->company_id ??= Company::default()?->id;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function backgroundCheck(): HasOne
    {
        return $this->hasOne(BackgroundCheck::class);
    }

    /**
     * Whether this person was asked for background verification and it has not
     * cleared yet. While it is true, attendance, leave and salary stay closed.
     */
    public function awaitingBackgroundCheck(): bool
    {
        return $this->backgroundCheck !== null && ! $this->backgroundCheck->isVerified();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /** Their full and final, once one has been started. */
    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }

    /** Every asset they have ever held, latest first. */
    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('issued_on')->latest('id');
    }

    /** The assets still with them — what a leaver has to hand back. */
    public function heldAssets(): HasMany
    {
        return $this->assetAssignments()->whereNull('returned_on');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function regularizations(): HasMany
    {
        return $this->hasMany(AttendanceRegularization::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveAccruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    public function leaveAllocations(): HasMany
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    // ------------------------------------------------------------------ scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOnRoll(Builder $query): Builder
    {
        return $query->whereIn('employment_status', [
            EmploymentStatus::Probation->value,
            EmploymentStatus::Permanent->value,
            EmploymentStatus::NoticePeriod->value,
        ]);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    /** Only the people this legal entity employs. */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    /** Restrict a listing to what the given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasOrganisationScope()) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('attendance.view-team') || $user->can('employees.view')) {
            return $query->where(function (Builder $q) use ($employee) {
                $q->where('branch_id', $employee->branch_id)
                    ->orWhere('reporting_to', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }

        return $query->where('id', $employee->id);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.str_replace('%', '\%', $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('employee_code', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    // ---------------------------------------------------------------- accessors

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.(string) $this->last_name);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1));
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    /** Their address on one line, for a letter or a certificate. */
    public function fullAddress(): string
    {
        return collect([
            $this->address_line1, $this->address_line2,
            $this->city, $this->state, $this->postal_code, $this->country,
        ])->filter()->implode(', ');
    }

    public function isOnRoll(): bool
    {
        return $this->status === 'active' && $this->employment_status->isOnRoll();
    }

    /**
     * Whether they were still on probation on a given date.
     *
     * Their confirmation date is the honest answer where it is recorded, so
     * asking about a month gone by gives the rate that was true then rather
     * than the one that is true today. Without it, their current employment
     * status has to stand in.
     */
    public function isOnProbation(?CarbonInterface $asOf = null): bool
    {
        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::today();

        if ($this->date_of_confirmation) {
            return $asOf->lt($this->date_of_confirmation);
        }

        return $this->employment_status === EmploymentStatus::Probation;
    }

    public function tenureInMonths(?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::today();
        $end = $this->date_of_exit && $this->date_of_exit->lt($asOf) ? $this->date_of_exit : $asOf;

        return (int) $this->date_of_joining->diffInMonths($end);
    }

    public function age(): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(Carbon::today()) : null;
    }

    /** The salary structure that applies on a given date. */
    public function salaryStructureOn(CarbonInterface|string|null $date = null): ?SalaryStructure
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return $this->salaryStructures()
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Employed (not yet exited) on the given date. */
    public function wasEmployedOn(CarbonInterface|string $date): bool
    {
        $date = Carbon::parse($date);

        if ($this->date_of_joining->gt($date)) {
            return false;
        }

        return ! ($this->date_of_exit && $this->date_of_exit->lt($date));
    }

    /** The user who should action this employee's approvals. */
    public function approverUser(): ?User
    {
        return $this->manager?->user ?? $this->branch?->manager?->user;
    }
}
