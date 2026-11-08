<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('app')->name('app.')->group(function () {
    // Developer styleguide; the controller returns 404 outside local and testing.
    Route::get('_styleguide', StyleguideController::class)->name('styleguide');
});
