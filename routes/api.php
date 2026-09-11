<?php

use App\Http\Controllers\Api\AssetApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackgroundCheckApiController;
use App\Http\Controllers\Api\DirectoryApiController;
use App\Http\Controllers\Api\HelpApiController;
use App\Http\Controllers\Api\LeaveApiController;
use App\Http\Controllers\Api\LetterApiController;
use App\Http\Controllers\Api\PayslipApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HRMS API (Sanctum personal access tokens)
|--------------------------------------------------------------------------
| Consumed by the mobile app and any third-party integration. Every route
| below /v1 except login requires a bearer token from POST /api/v1/login.
*/

Route::prefix('v1')->name('api.')->group(function () {

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        // ------------------------------------------------------------ account
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');

        // --------------------------------------------------------- attendance
        // Closed until a new joiner's background verification clears.
        Route::prefix('attendance')->name('attendance.')->middleware('bgv.cleared')->group(function () {
            Route::get('/', [AttendanceApiController::class, 'index'])->name('index');
            Route::get('today', [AttendanceApiController::class, 'today'])->name('today');
            Route::get('summary', [AttendanceApiController::class, 'summary'])->name('summary');
            Route::post('check-in', [AttendanceApiController::class, 'checkIn'])->name('check-in');
            Route::post('check-out', [AttendanceApiController::class, 'checkOut'])->name('check-out');
            Route::get('break-reasons', [AttendanceApiController::class, 'breakReasons'])->name('break-reasons');
            Route::post('break/start', [AttendanceApiController::class, 'startBreak'])->name('break.start');
            Route::post('break/end', [AttendanceApiController::class, 'endBreak'])->name('break.end');
        });

        // -------------------------------------------------------------- leave
        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('pending-approvals', [LeaveApiController::class, 'pendingApprovals'])->name('pending');
            Route::post('{leave}/approve', [LeaveApiController::class, 'approve'])->name('approve');
            Route::post('{leave}/reject', [LeaveApiController::class, 'reject'])->name('reject');

            // One's own leave waits for verification to clear; deciding
            // other people's does not.
            Route::middleware('bgv.cleared')->group(function () {
                Route::get('/', [LeaveApiController::class, 'index'])->name('index');
                Route::get('types', [LeaveApiController::class, 'types'])->name('types');
                Route::get('balance', [LeaveApiController::class, 'balance'])->name('balance');
                Route::post('/', [LeaveApiController::class, 'store'])->name('store');
                Route::get('{leave}', [LeaveApiController::class, 'show'])->name('show');
                Route::post('{leave}/cancel', [LeaveApiController::class, 'cancel'])->name('cancel');
            });
        });

        // ----------------------------------------------------------- payslips
        Route::middleware('bgv.cleared')->group(function () {
            Route::get('payslips', [PayslipApiController::class, 'index'])->name('payslips.index');
            Route::get('payslips/{payslip}', [PayslipApiController::class, 'show'])->name('payslips.show');
            Route::get('payslips/{payslip}/download', [PayslipApiController::class, 'download'])->name('payslips.download');
        });

        // ------------------------------------------------------------ letters
        // Behind bgv.cleared like payslips: a joiner still being verified has
        // nothing issued to them yet anyway.
        Route::middleware('bgv.cleared')->prefix('letters')->name('letters.')->group(function () {
            Route::get('/', [LetterApiController::class, 'index'])->name('index');
            Route::get('types', [LetterApiController::class, 'types'])->name('types');
            Route::get('{letter}', [LetterApiController::class, 'show'])->name('show');
            Route::get('{letter}/download', [LetterApiController::class, 'download'])->name('download');
        });

        // -------------------------------------------- background verification
        // Deliberately NOT behind bgv.cleared: clearing it is what these are
        // for, and a joiner doing so is exactly who has not cleared it yet.
        Route::prefix('background-check')->name('background-check.')->group(function () {
            Route::get('/', [BackgroundCheckApiController::class, 'show'])->name('show');
            Route::post('submit', [BackgroundCheckApiController::class, 'submit'])->name('submit');
            Route::post('items/{item}', [BackgroundCheckApiController::class, 'upload'])
                ->middleware('throttle:30,1')->name('upload');
            Route::get('items/{item}/document', [BackgroundCheckApiController::class, 'document'])
                ->name('document');
        });

        // ------------------------------------------------------------- assets
        // Read-only: handing something back goes through whoever receives it.
        Route::get('my-assets', [AssetApiController::class, 'index'])->name('my-assets.index');
        Route::get('my-assets/history', [AssetApiController::class, 'history'])->name('my-assets.history');

        // ------------------------------------------------------- help guides
        Route::get('help', [HelpApiController::class, 'index'])->name('help.index');
        Route::get('help/{article}', [HelpApiController::class, 'show'])->name('help.show');

        // ---------------------------------------------------------- directory
        Route::get('dashboard', [DirectoryApiController::class, 'dashboard'])->name('dashboard');
        Route::get('employees', [DirectoryApiController::class, 'employees'])->name('employees.index');
        Route::get('holidays', [DirectoryApiController::class, 'holidays'])->name('holidays.index');
        Route::get('announcements', [DirectoryApiController::class, 'announcements'])->name('announcements.index');
        Route::get('notifications', [DirectoryApiController::class, 'notifications'])->name('notifications.index');
        Route::post('notifications/{id}/read', [DirectoryApiController::class, 'markNotificationRead'])
            ->name('notifications.read');
    });
});
