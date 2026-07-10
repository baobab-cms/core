<?php

declare(strict_types=1);

use Baobab\Admin\Access\Http\Controllers\AccessMatrixController;
use Baobab\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('baobab::admin.dashboard'))->name('dashboard');

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
