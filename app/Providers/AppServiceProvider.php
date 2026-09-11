<?php

namespace App\Providers;

use App\Listeners\LogFailedMailJob;
use App\Listeners\RecordPayslipDelivery;
use App\Listeners\RedirectOutgoingMail;
use App\Listeners\ResetMailerBeforeJob;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\TaxDeclaration;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\BranchPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DesignationPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\LetterPolicy;
use App\Policies\TaxDeclarationPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PayslipPolicy;
use App\Policies\UserPolicy;
use App\Services\MailSettings;
use App\Support\Roles;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [
        Employee::class => EmployeePolicy::class,
        Branch::class => BranchPolicy::class,
        Department::class => DepartmentPolicy::class,
        Designation::class => DesignationPolicy::class,
        Attendance::class => AttendancePolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        Payroll::class => PayrollPolicy::class,
        Payslip::class => PayslipPolicy::class,
        Announcement::class => AnnouncementPolicy::class,
        Letter::class => LetterPolicy::class,
        TaxDeclaration::class => TaxDeclarationPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        /*
         * A super admin bypasses every individual permission check, with one
         * deliberate exception: a user may never delete their own account.
         * Returning null there lets the policy run and refuse.
         */
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->hasRole(Roles::SUPER_ADMIN)) {
                return null;
            }

            $target = $arguments[0] ?? null;

            if ($ability === 'delete' && $target instanceof User && $target->is($user)) {
                return null;
            }

            return true;
        });

        // Anywhere but production, every message goes to one inbox instead of
        // to the person it names. Registered before anything else that touches
        // mail, so a redirected message is redirected whatever sent it.
        Event::listen(MessageSending::class, RedirectOutgoingMail::class);

        // Mail is queued, so delivery and failure are both reported by event.
        Event::listen(MessageSent::class, RecordPayslipDelivery::class);
        Event::listen(JobFailed::class, LogFailedMailJob::class);
        Event::listen(JobProcessing::class, ResetMailerBeforeJob::class);

        $this->applyStoredSettings();

        if (! in_array(request()->getHost(), ['127.0.0.1', '::1'])) {
//            URL::forceScheme('https');
        }
    }

    /**
     * Overlay database-managed settings on top of the config so the
     * application name, currency and mail sender stay editable from the UI.
     */
    protected function applyStoredSettings(): void
    {
        if ($this->app->runningInConsole() && ! $this->settingsTableReady()) {
            return;
        }

        try {
            $settings = Setting::allValues();
        } catch (\Throwable) {
            return;
        }

        if ($name = $settings['company_name'] ?? null) {
            config(['app.name' => $name]);
        }

        if ($from = $settings['mail_from_address'] ?? null) {
            config(['mail.from.address' => $from]);
        }

        if ($fromName = $settings['mail_from_name'] ?? null) {
            config(['mail.from.name' => $fromName]);
        }

        if ($timezone = $settings['timezone'] ?? null) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        // Where mail actually goes, when that was decided on the settings
        // screen rather than in the environment file.
        app(MailSettings::class)->apply();
    }

    protected function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
