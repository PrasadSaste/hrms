<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRegularizationController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BackgroundCheckController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\HelpArticleController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\LeaveAllocationController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LetterTemplateController;
use App\Http\Controllers\MyAssetController;
use App\Http\Controllers\MyBackgroundCheckController;
use App\Http\Controllers\MyLetterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\TaxDeclarationController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\SalaryStructureController;
use App\Http\Controllers\SelfAssistanceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// The sign-in page and the browser tab show these, so they stay outside the
// auth middleware.
Route::get('branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');
Route::get('branding/favicon', [BrandingController::class, 'favicon'])->name('branding.favicon');

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
|
| Reachable only while the system is not set up. The wizard writes database
| credentials and creates an account that can see every salary in the company,
| all without anybody signing in, so `installed` closes it the moment the lock
| file is written and nothing here answers again.
|
*/
Route::prefix('install')->name('install.')->middleware('installed')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');

    Route::get('database', [InstallController::class, 'database'])->name('database');
    Route::post('database', [InstallController::class, 'storeDatabase'])
        ->middleware('throttle:20,1')->name('database.store');

    Route::get('organisation', [InstallController::class, 'organisation'])->name('organisation');
    Route::post('organisation', [InstallController::class, 'storeOrganisation'])->name('organisation.store');

    Route::get('administrator', [InstallController::class, 'administrator'])->name('administrator');
    Route::post('administrator', [InstallController::class, 'storeAdministrator'])
        ->middleware('throttle:10,1')->name('administrator.store');
});

// Outside that group deliberately: by the time it is shown the system is
// installed, and it is the one page that has to survive that. It shows itself
// only to the browser that just finished, and only once.
Route::get('install/complete', [InstallController::class, 'complete'])->name('install.complete');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Temporary-password flow runs before the password.change middleware kicks in.
    Route::get('change-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    // Answering the challenge sits outside the guard, or passing it would
    // require having already passed it.
    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('two-factor.verify');

    Route::middleware(['password.change', 'two-factor', 'log.activity'])->group(function () {

        // ---------------------------------------------------- second factor
        // Inside the guard on purpose. The middleware lets somebody who must
        // enrol reach `setup` and `confirm`; it does not let somebody who has
        // a second factor and has not answered it reach anything here, or the
        // holder of a stolen password could simply turn it off.
        Route::get('two-factor', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->name('two-factor.recovery');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // ------------------------------------------------------------- profile
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/personal', [ProfileController::class, 'updatePersonal'])->name('profile.personal');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        // ------------------------------------------------------ self assistance
        Route::get('self-assistance', [SelfAssistanceController::class, 'index'])->name('self-assistance.index');
        Route::get('self-assistance/{article}', [SelfAssistanceController::class, 'show'])
            ->name('self-assistance.show');

        // ------------------------------------------------------- notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // --------------------------------------------------- payroll companies
        Route::resource('companies', CompanyController::class)->except('show');

        // Who may sign for each entity: a list, one of them the default.
        Route::get('companies/{company}/signatories', [SignatoryController::class, 'index'])
            ->name('companies.signatories.index');
        Route::post('companies/{company}/signatories', [SignatoryController::class, 'store'])
            ->name('companies.signatories.store');
        Route::put('companies/{company}/signatories/{signatory}', [SignatoryController::class, 'update'])
            ->name('companies.signatories.update');
        Route::post('companies/{company}/signatories/{signatory}/default', [SignatoryController::class, 'makeDefault'])
            ->name('companies.signatories.default');
        Route::delete('companies/{company}/signatories/{signatory}', [SignatoryController::class, 'destroy'])
            ->name('companies.signatories.destroy');
        Route::get('companies/{company}/signatories/{signatory}/signature', [SignatoryController::class, 'signature'])
            ->name('companies.signatories.signature');

        // ------------------------------------------------------------ branches
        Route::resource('branches', BranchController::class);
        Route::resource('departments', DepartmentController::class);
        Route::resource('designations', DesignationController::class)->except('show');

        // ----------------------------------------------------------- employees
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::resource('employees', EmployeeController::class);
        Route::post('employees/{employee}/account', [EmployeeController::class, 'provisionAccount'])
            ->name('employees.account');
        Route::post('employees/{employee}/offboard', [EmployeeController::class, 'offboard'])
            ->name('employees.offboard');
        Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
            ->name('employees.documents.store');
        Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])
            ->name('employees.documents.download');
        Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->name('employees.documents.destroy');

        // ------------------------------------------- background verification
        Route::get('my-verification', [MyBackgroundCheckController::class, 'edit'])
            ->name('my-verification.edit');
        Route::post('my-verification/items/{item}', [MyBackgroundCheckController::class, 'upload'])
            ->name('my-verification.upload');
        Route::post('my-verification/submit', [MyBackgroundCheckController::class, 'submit'])
            ->name('my-verification.submit');

        Route::prefix('background-checks')->name('background-checks.')->group(function () {
            Route::get('/', [BackgroundCheckController::class, 'index'])->name('index');
            Route::post('invite', [BackgroundCheckController::class, 'invite'])->name('invite');
            Route::get('{check}', [BackgroundCheckController::class, 'show'])->name('show');
            Route::post('{check}/request-changes', [BackgroundCheckController::class, 'requestChanges'])
                ->name('request-changes');
            Route::post('{check}/complete', [BackgroundCheckController::class, 'complete'])->name('complete');
            Route::put('{check}/requirements', [BackgroundCheckController::class, 'updateRequirements'])
                ->name('requirements');
            Route::post('items/{item}/review', [BackgroundCheckController::class, 'reviewItem'])
                ->name('items.review');
            Route::get('items/{item}/document', [BackgroundCheckController::class, 'document'])
                ->name('items.document');
        });

        // ---------------------------------------------------------- attendance
        Route::prefix('attendance')->name('attendance.')->group(function () {
            // A new joiner's own attendance waits for their verification to clear.
            // Breaks belong to the same day, so they wait with it.
            Route::middleware('bgv.cleared')->group(function () {
                Route::get('/', [AttendanceController::class, 'index'])->name('index');
                Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
                Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
                Route::post('break/start', [AttendanceController::class, 'startBreak'])->name('break.start');
                Route::post('break/end', [AttendanceController::class, 'endBreak'])->name('break.end');
            });
            Route::get('daily', [AttendanceController::class, 'daily'])->name('daily');
            Route::get('location-alerts', [AttendanceController::class, 'locationAlerts'])
                ->name('location-alerts');
            Route::get('create', [AttendanceController::class, 'create'])->name('create');
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            Route::get('{attendance}/edit', [AttendanceController::class, 'edit'])->name('edit');
            Route::put('{attendance}', [AttendanceController::class, 'update'])->name('update');
            Route::delete('{attendance}', [AttendanceController::class, 'destroy'])->name('destroy');
            Route::get('employee/{employee}', [AttendanceController::class, 'employee'])->name('employee');
            Route::get('employee/{employee}/export', [AttendanceController::class, 'export'])->name('export');

            Route::middleware('bgv.cleared')->group(function () {
                Route::get('regularizations', [AttendanceRegularizationController::class, 'index'])
                    ->name('regularizations.index');
                Route::post('regularizations', [AttendanceRegularizationController::class, 'store'])
                    ->name('regularizations.store');
            });
            Route::post('regularizations/{regularization}/approve', [AttendanceRegularizationController::class, 'approve'])
                ->name('regularizations.approve');
            Route::post('regularizations/{regularization}/reject', [AttendanceRegularizationController::class, 'reject'])
                ->name('regularizations.reject');
        });

        // --------------------------------------------------------------- leave
        Route::get('leave/calendar', [LeaveController::class, 'calendar'])->name('leave.calendar');
        Route::get('leave/export', [LeaveController::class, 'export'])->name('leave.export');
        Route::post('leave/{leave}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::post('leave/{leave}/reject', [LeaveController::class, 'reject'])->name('leave.reject');

        // Applying for and looking at leave waits for verification to clear;
        // deciding other people's does not.
        Route::middleware('bgv.cleared')->group(function () {
            Route::get('leave/balance', [LeaveController::class, 'balance'])->name('leave.balance');
            Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
            Route::get('leave/create', [LeaveController::class, 'create'])->name('leave.create');
            Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
            Route::get('leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
            Route::post('leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
        });

        Route::resource('leave-types', LeaveTypeController::class)->except('show')
            ->parameters(['leave-types' => 'leaveType']);

        Route::get('leave-allocations', [LeaveAllocationController::class, 'index'])->name('leave-allocations.index');
        Route::post('leave-allocations', [LeaveAllocationController::class, 'store'])->name('leave-allocations.store');
        Route::put('leave-allocations/{allocation}', [LeaveAllocationController::class, 'update'])
            ->name('leave-allocations.update');
        Route::post('leave-allocations/bulk', [LeaveAllocationController::class, 'bulkAllocate'])
            ->name('leave-allocations.bulk');

        // ------------------------------------------------------------- payroll
        Route::prefix('payroll')->name('payroll.')->group(function () {
            Route::get('/', [PayrollController::class, 'index'])->name('index');
            Route::get('create', [PayrollController::class, 'create'])->name('create');
            Route::post('/', [PayrollController::class, 'store'])->name('store');
            Route::get('{payroll}', [PayrollController::class, 'show'])->name('show');
            Route::put('{payroll}', [PayrollController::class, 'update'])->name('update');
            Route::delete('{payroll}', [PayrollController::class, 'destroy'])->name('destroy');
            Route::post('{payroll}/generate', [PayrollController::class, 'generate'])->name('generate');
            Route::post('{payroll}/submit', [PayrollController::class, 'submit'])->name('submit');
            Route::post('{payroll}/approve', [PayrollController::class, 'approve'])->name('approve');
            Route::post('{payroll}/mark-paid', [PayrollController::class, 'markPaid'])->name('mark-paid');
            Route::post('{payroll}/email', [PayrollController::class, 'emailPayslips'])->name('email');
            Route::get('{payroll}/export', [PayrollController::class, 'export'])->name('export');
            Route::get('{payroll}/bank-file', [PayrollController::class, 'bankFile'])->name('bank-file');
            Route::post('{payroll}/bank-file', [PayrollController::class, 'downloadBankFile'])->name('bank-file.download');
        });

        Route::resource('salary-components', SalaryComponentController::class)->except('show')
            ->parameters(['salary-components' => 'salaryComponent']);
        Route::resource('salary-structures', SalaryStructureController::class)
            ->parameters(['salary-structures' => 'salaryStructure']);

        // ------------------------------------------------------------ payslips
        Route::middleware('bgv.cleared')->group(function () {
            Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
            Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
            Route::get('payslips/{payslip}/download', [PayslipController::class, 'download'])->name('payslips.download');
            Route::get('payslips/{payslip}/print', [PayslipController::class, 'stream'])->name('payslips.print');
        });
        // --------------------------------------------------------- income tax
        Route::prefix('tax')->name('tax.')->group(function () {
            Route::get('/', [TaxDeclarationController::class, 'index'])->name('index');
            Route::get('mine', [TaxDeclarationController::class, 'mine'])->name('mine');
            Route::get('computation/{employee}', [TaxDeclarationController::class, 'computation'])->name('computation');
            Route::get('statement/{employee}', [TaxDeclarationController::class, 'statement'])->name('statement');
            Route::get('proof/{item}', [TaxDeclarationController::class, 'proof'])->name('proof');
            Route::get('{declaration}', [TaxDeclarationController::class, 'show'])->name('show');
            Route::put('{declaration}', [TaxDeclarationController::class, 'update'])->name('update');
            Route::post('{declaration}/proof', [TaxDeclarationController::class, 'uploadProof'])->name('proof.upload');
            Route::post('{declaration}/verify', [TaxDeclarationController::class, 'verify'])->name('verify');
            Route::post('{declaration}/send-back', [TaxDeclarationController::class, 'sendBack'])->name('send-back');
        });

        Route::put('payslips/{payslip}', [PayslipController::class, 'update'])->name('payslips.update');
        Route::post('payslips/{payslip}/email', [PayslipController::class, 'email'])->name('payslips.email');

        // ------------------------------------------------- holidays and shifts
        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::resource('shifts', ShiftController::class)->except('show');

        // ------------------------------------------------------- announcements
        Route::resource('announcements', AnnouncementController::class);

        // ------------------------------------------------------------- reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('attendance', [ReportController::class, 'attendance'])->name('attendance');
            Route::get('attendance/export', [ReportController::class, 'attendanceExport'])->name('attendance.export');

            // The reports that read sessions and breaks rather than the summary.
            Route::get('breaks', [AttendanceReportController::class, 'breaks'])->name('breaks');
            Route::get('breaks/export', [AttendanceReportController::class, 'breaksExport'])->name('breaks.export');
            Route::get('attendance/daily', [AttendanceReportController::class, 'daily'])->name('attendance.daily');
            Route::get('attendance/daily/export', [AttendanceReportController::class, 'dailyExport'])
                ->name('attendance.daily.export');
            Route::get('attendance/monthly', [AttendanceReportController::class, 'monthly'])->name('attendance.monthly');
            Route::get('attendance/monthly/export', [AttendanceReportController::class, 'monthlyExport'])
                ->name('attendance.monthly.export');
            Route::get('attendance/in-out', [AttendanceReportController::class, 'inOut'])->name('attendance.in-out');
            Route::get('attendance/in-out/export', [AttendanceReportController::class, 'inOutExport'])
                ->name('attendance.in-out.export');
            Route::get('leave', [ReportController::class, 'leave'])->name('leave');
            Route::get('payroll', [ReportController::class, 'payroll'])->name('payroll');
            Route::get('employees', [ReportController::class, 'employees'])->name('employees');
        });

        // ----------------------------------------------------------- letters
        // Offer, appointment, confirmation, increment, experience and the rest.
        // Issuing takes a permission; reading your own does not.
        // -------------------------------------------------------- settlements
        Route::get('my-settlement', [SettlementController::class, 'mine'])->name('my-settlement');

        Route::prefix('settlements')->name('settlements.')->group(function () {
            Route::get('/', [SettlementController::class, 'index'])->name('index');
            Route::get('create', [SettlementController::class, 'create'])->name('create');
            Route::post('/', [SettlementController::class, 'store'])->name('store');
            Route::get('{settlement}', [SettlementController::class, 'show'])->name('show');
            Route::get('{settlement}/download', [SettlementController::class, 'download'])->name('download');
            Route::post('{settlement}/lines', [SettlementController::class, 'addLine'])->name('lines.store');
            Route::delete('{settlement}/lines/{line}', [SettlementController::class, 'removeLine'])->name('lines.destroy');
            Route::post('{settlement}/approve', [SettlementController::class, 'approve'])->name('approve');
            Route::post('{settlement}/paid', [SettlementController::class, 'markPaid'])->name('paid');
        });

        // ------------------------------------------------------------- assets
        Route::get('my-assets', [MyAssetController::class, 'index'])->name('my-assets.index');

        Route::resource('assets', AssetController::class);
        Route::post('assets/{asset}/issue', [AssetController::class, 'issue'])->name('assets.issue');
        Route::post('assets/{asset}/return', [AssetController::class, 'return'])->name('assets.return');

        Route::get('my-letters', [MyLetterController::class, 'index'])->name('my-letters.index');
        Route::get('my-letters/{letter}/download', [MyLetterController::class, 'download'])
            ->name('my-letters.download');

        Route::prefix('letters')->name('letters.')->group(function () {
            Route::get('/', [LetterController::class, 'index'])->name('index');
            Route::get('issue', [LetterController::class, 'create'])->name('create');
            Route::post('/', [LetterController::class, 'store'])->name('store');
            Route::get('{letter}', [LetterController::class, 'show'])->name('show');
            Route::get('{letter}/download', [LetterController::class, 'download'])->name('download');
            Route::post('{letter}/email', [LetterController::class, 'email'])->name('email');
            Route::delete('{letter}', [LetterController::class, 'destroy'])->name('destroy');
        });

        // The wording of each kind of letter.
        Route::prefix('letter-templates')->name('letter-templates.')->group(function () {
            Route::get('/', [LetterTemplateController::class, 'index'])->name('index');
            Route::get('{type}/edit', [LetterTemplateController::class, 'edit'])->name('edit');
            Route::put('{type}', [LetterTemplateController::class, 'update'])->name('update');
            Route::match(['delete', 'put'], '{type}/reset', [LetterTemplateController::class, 'destroy'])->name('reset');
            Route::post('{type}/preview', [LetterTemplateController::class, 'preview'])->name('preview');
        });

        // ------------------------------------------------------ administration
        // Bringing an old HRMS in from spreadsheets. The file is read and
        // reported on first; only the commit route writes anything.
        Route::prefix('data-import')->name('data-import.')->group(function () {
            Route::get('/', [DataImportController::class, 'index'])->name('index');
            Route::get('runs/{dataImport}', [DataImportController::class, 'review'])->name('review');
            Route::get('runs/{dataImport}/rows-to-fix', [DataImportController::class, 'errors'])->name('errors');
            Route::post('runs/{dataImport}/import', [DataImportController::class, 'commit'])->name('commit');
            Route::get('{type}', [DataImportController::class, 'show'])->name('show');
            Route::get('{type}/template', [DataImportController::class, 'template'])->name('template');
            Route::post('{type}', [DataImportController::class, 'store'])->name('store');
        });

        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::post('users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])
            ->name('users.reset-two-factor');

        Route::resource('roles', RoleController::class)->except('show');

        // The notification console: what every message says and where it goes.
        Route::prefix('notification-templates')->name('notification-templates.')->group(function () {
            Route::get('/', [NotificationTemplateController::class, 'index'])->name('index');
            Route::get('{key}/edit', [NotificationTemplateController::class, 'edit'])->name('edit');
            Route::put('{key}', [NotificationTemplateController::class, 'update'])->name('update');
            Route::match(['delete', 'put'], '{key}/reset', [NotificationTemplateController::class, 'destroy'])->name('reset');
            Route::post('{key}/channels', [NotificationTemplateController::class, 'channels'])->name('channels');
            Route::post('{key}/preview', [NotificationTemplateController::class, 'preview'])->name('preview');
            // Also accepts PUT so the editor's "send a test" button can post the
            // wording on screen straight from the same form.
            Route::match(['post', 'put'], '{key}/test', [NotificationTemplateController::class, 'test'])->name('test');
        });

        // The Self Assistance guides themselves.
        Route::resource('help-articles', HelpArticleController::class)
            ->except('show')
            ->parameters(['help-articles' => 'article']);

        // ---------------------------------------------------------- automations
        Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
        Route::put('automations/{key}', [AutomationController::class, 'update'])->name('automations.update');
        Route::post('automations/{key}/run', [AutomationController::class, 'run'])
            ->middleware('throttle:12,1')->name('automations.run');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        // Throttled: this one reaches out to a mail server on each press.
        Route::post('settings/test-email', [SettingController::class, 'testMail'])
            ->middleware('throttle:6,1')->name('settings.test-email');

        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
    });
});
