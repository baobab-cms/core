<?php

declare(strict_types=1);

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
