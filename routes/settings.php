<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\RequirePasswordIfSet;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('conta', '/conta/perfil');

    Route::get('conta/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('conta/perfil', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('conta/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('conta/seguranca', [SecurityController::class, 'edit'])
        ->middleware(RequirePasswordIfSet::class)
        ->name('security.edit');

    Route::put('conta/senha', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('conta/aparencia', 'settings/appearance')->name('appearance.edit');
});
