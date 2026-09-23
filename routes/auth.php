<?php

declare(strict_types=1);

use Baobab\Admin\Users\Http\Controllers\ImpersonationController;
use Baobab\Auth\Http\Controllers\ForgotPasswordController;
use Baobab\Auth\Http\Controllers\InvitationController;
use Baobab\Auth\Http\Controllers\LoginController;
use Baobab\Auth\Http\Controllers\ResetPasswordController;
use Baobab\Auth\Http\Controllers\TwoFactorChallengeController;
use Baobab\Install\Http\Middleware\RedirectToInstaller;
use Illuminate\Support\Facades\Route;

/*
 * `RedirectToInstaller` en tête des écrans invités — recette du 3 septembre
 * 2026, n° 242.
 *
 * Ces vues rendent `layouts/guest.blade.php`, donc `<x-baobab::design-tokens />`,
 * qui lit la base. Sur une archive décompressée, `/login` répondait par une
 * exception de base de données là où l'installateur attendait à un clic. Le
 * middleware garde la règle de la C3a : il ne détourne qu'un site sans lock
 * **et** sans base atteignable, c'est-à-dire un site qui ne peut rien servir.
 */
Route::middleware([RedirectToInstaller::class, 'web', 'guest:baobab'])->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1');

    // Spec 04 §9, décision 7 : mot de passe oublié.
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');

    // Spec 05 §5, décision 5 : l'invité choisit son mot de passe.
    Route::get('/invitation/{token}', [InvitationController::class, 'create'])->name('invitation.accept');
    Route::post('/invitation', [InvitationController::class, 'store'])->middleware('throttle:5,1')->name('invitation.store');
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
