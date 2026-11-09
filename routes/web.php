<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OnboardingController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StyleguideController;
use App\Http\Controllers\Portal\HomeController as PortalHomeController;
use App\Http\Controllers\Public\StaffInvitationController;
use App\Http\Controllers\System\InstallController;
use App\Http\Controllers\System\UpgradeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Web installer: only before storage/app/installed.lock exists, only with ?key=RENTORA_INSTALL_KEY.
Route::prefix('install')->name('install.')->middleware('installer')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('organization', [InstallController::class, 'store'])->name('organization');
});

Route::middleware(['auth', 'active', 'role:owner'])->group(function () {
    Route::get('system/upgrade', [UpgradeController::class, 'show'])->name('system.upgrade');
    Route::post('system/upgrade', [UpgradeController::class, 'run'])->name('system.upgrade.run');
});

Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('invite/staff/{user}', [StaffInvitationController::class, 'show'])->name('invite.staff');
    Route::post('invite/staff/{user}', [StaffInvitationController::class, 'store'])->name('invite.staff.accept');
});

// Landlord console.
Route::prefix('app')->name('app.')->middleware(['auth', 'active', 'role:owner,manager,caretaker', 'onboarded'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('setup/{step}', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('setup/{step}', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::middleware('can:manage-staff')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{member}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('staff/{member}/resend', [StaffController::class, 'resend'])->name('staff.resend');
    });
});

// Developer styleguide; the controller returns 404 outside local and testing.
Route::get('app/_styleguide', StyleguideController::class)->name('app.styleguide');

// Tenant portal.
Route::prefix('portal')->name('portal.')->middleware(['auth', 'active', 'role:tenant'])->group(function () {
    Route::get('/', PortalHomeController::class)->name('home');
});
