<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('conta', AccountController::class)->name('account');

    Route::get('conta/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('conta/perfil', [ProfileController::class, 'update'])->name('profile.update');

    // POST (e não PUT) para permitir envio de arquivo via multipart.
    Route::post('conta/foto', [AvatarController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('avatar.update');
    Route::delete('conta/foto', [AvatarController::class, 'destroy'])->name('avatar.destroy');

    // A aparência agora fica na própria página do perfil.
    Route::redirect('conta/aparencia', '/conta');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('conta/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Sem pedir a senha de novo para abrir: a troca já exige a senha atual.
    Route::get('conta/seguranca', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('conta/senha', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
