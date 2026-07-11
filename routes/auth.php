<?php

declare(strict_types=1);

use Baobab\Admin\Users\Http\Controllers\ImpersonationController;
use Baobab\Auth\Http\Controllers\LoginController;
use Baobab\Auth\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest:baobab'])->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware(['web', 'auth:baobab'])
    ->name('logout');

// Hors du groupe admin (pas de can:baobab.admin.access) : la cible usurpée
// n'a pas forcément cette permission elle-même, l'arrêt doit rester
// atteignable quoi qu'il arrive (spec 04 §9.1).
Route::post('/impersonation/stop', [ImpersonationController::class, 'destroy'])
    ->middleware(['web', 'auth:baobab'])
    ->name('impersonation.stop');
