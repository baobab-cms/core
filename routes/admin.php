<?php

declare(strict_types=1);

use Baobab\Admin\Access\Http\Controllers\AccessMatrixController;
use Baobab\Admin\Account\Http\Controllers\SecurityController;
use Baobab\Admin\Users\Http\Controllers\ImpersonationController;
use Baobab\Admin\Users\Http\Controllers\UserController;
use Baobab\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('baobab::admin.dashboard'))->name('dashboard');

Route::prefix('account/security')
    ->name('account.security.')
    ->group(function (): void {
        Route::get('/', [SecurityController::class, 'show'])->name('show');
        Route::post('/enable', [SecurityController::class, 'enable'])->name('enable');
        Route::post('/confirm', [SecurityController::class, 'confirm'])->name('confirm');
        Route::post('/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
        Route::post('/disable', [SecurityController::class, 'disable'])->name('disable');
    });

Route::middleware('can:baobab.audit.view')
    ->prefix('audit')
    ->name('audit.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
    });

Route::middleware('can:baobab.access.manage')
    ->prefix('access')
    ->name('access.')
    ->group(function (): void {
        Route::get('/', [AccessMatrixController::class, 'index'])->name('index');
        Route::post('/roles', [AccessMatrixController::class, 'store'])->name('roles.store');
        Route::post('/{role}/permissions/{permission}', [AccessMatrixController::class, 'toggle'])->name('toggle');
    });

Route::middleware('can:baobab.users.impersonate')
    ->prefix('users')
    ->name('users.')
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/{user}/impersonate', [ImpersonationController::class, 'store'])->name('impersonate');
    });
