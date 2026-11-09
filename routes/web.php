<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LeaseController;
use App\Http\Controllers\Admin\LeaseWizardController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\OnboardingController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\QuickAddUnitsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StyleguideController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UnitPhotoController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Portal\HomeController as PortalHomeController;
use App\Http\Controllers\Public\StaffInvitationController;
use App\Http\Controllers\Public\TenantInvitationController;
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
    Route::get('invite/{tenant}', [TenantInvitationController::class, 'show'])->whereNumber('tenant')->name('invite.tenant');
    Route::post('invite/{tenant}', [TenantInvitationController::class, 'store'])->whereNumber('tenant')->name('invite.tenant.accept');
});

// Landlord console.
Route::prefix('app')->name('app.')->middleware(['auth', 'active', 'role:owner,manager,caretaker', 'onboarded'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('setup/{step}', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('setup/{step}', [OnboardingController::class, 'store'])->name('onboarding.store');

    // Portfolio: owners and managers manage; caretakers may read units (policies decide per action).
    Route::resource('properties', PropertyController::class);
    Route::get('properties/{property}/quick-add', [QuickAddUnitsController::class, 'create'])->name('properties.quick-add');
    Route::post('properties/{property}/quick-add', [QuickAddUnitsController::class, 'store'])->name('properties.quick-add.store');
    Route::resource('units', UnitController::class);
    Route::post('units/{unit}/photos', [UnitPhotoController::class, 'store'])->name('units.photos.store');
    Route::patch('units/{unit}/photos/{photo}/move', [UnitPhotoController::class, 'move'])->name('units.photos.move');
    Route::delete('units/{unit}/photos/{photo}', [UnitPhotoController::class, 'destroy'])->name('units.photos.destroy');
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::patch('listings/{unit}', [ListingController::class, 'toggle'])->name('listings.toggle');
    // People: tenants (caretakers read contact details) and leases.
    Route::resource('tenants', TenantController::class);
    Route::post('tenants/{tenant}/invite', [TenantController::class, 'invite'])->name('tenants.invite');
    Route::get('leases/new', [LeaseWizardController::class, 'start'])->name('leases.wizard.start');
    Route::get('leases/new/{step}', [LeaseWizardController::class, 'show'])->name('leases.wizard.show');
    Route::post('leases/new/{step}', [LeaseWizardController::class, 'store'])->name('leases.wizard.store');
    Route::get('leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::post('leases/{lease}/activate', [LeaseController::class, 'activate'])->name('leases.activate');
    Route::post('leases/{lease}/renew', [LeaseController::class, 'renew'])->name('leases.renew');
    Route::post('leases/{lease}/end', [LeaseController::class, 'end'])->name('leases.end');
    Route::post('leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');

    // Money: owners and managers only (caretakers never reach these routes).
    Route::middleware('can:manage-money')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    });

    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::middleware('can:manage-staff')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{member}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('staff/{member}/resend', [StaffController::class, 'resend'])->name('staff.resend');
    });
});

// Private files, streamed after a policy check (spec 11).
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('files/documents/{document}', [FileController::class, 'document'])->name('files.documents');
});

// Developer styleguide; the controller returns 404 outside local and testing.
Route::get('app/_styleguide', StyleguideController::class)->name('app.styleguide');

// Tenant portal.
Route::prefix('portal')->name('portal.')->middleware(['auth', 'active', 'role:tenant'])->group(function () {
    Route::get('/', PortalHomeController::class)->name('home');
});
