<?php

declare(strict_types=1);

use Baobab\Admin\Access\Http\Controllers\AccessMatrixController;
use Baobab\Admin\Account\Http\Controllers\SecurityController;
use Baobab\Admin\Content\Http\Controllers\ContentController;
use Baobab\Admin\Media\Http\Controllers\MediaController;
use Baobab\Admin\Media\Http\Controllers\MediaFolderController;
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

Route::prefix('media')
    ->name('media.')
    ->group(function (): void {
        Route::get('/', [MediaController::class, 'index'])->name('index');
        Route::post('/', [MediaController::class, 'store'])->name('store');
        Route::post('/chunk', [MediaController::class, 'storeChunk'])->name('chunk');
        Route::post('/external', [MediaController::class, 'storeExternal'])->name('external.store');
        Route::post('/move', [MediaController::class, 'move'])->name('move');
        Route::post('/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::post('/bulk-restore', [MediaController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-force-destroy', [MediaController::class, 'bulkForceDestroy'])->name('bulk-force-destroy');
        Route::post('/folders', [MediaFolderController::class, 'store'])->name('folders.store');
        Route::put('/folders/{folder}', [MediaFolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [MediaFolderController::class, 'destroy'])->name('folders.destroy');
        Route::get('/{media}', [MediaController::class, 'show'])->name('show');
        Route::patch('/{media}', [MediaController::class, 'update'])->name('update');
        Route::patch('/{media}/focal-point', [MediaController::class, 'updateFocalPoint'])->name('focal-point.update');
        Route::post('/{media}/transform', [MediaController::class, 'storeTransform'])->name('transform.store');
        Route::delete('/{media}/transform', [MediaController::class, 'destroyTransform'])->name('transform.destroy');
        Route::post('/{media}/restore', [MediaController::class, 'restore'])->name('restore')->withTrashed();
        Route::delete('/{media}/force', [MediaController::class, 'forceDestroy'])->name('force-destroy')->withTrashed();
        Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
    });

Route::prefix('content/{contentType}')
    ->name('content.')
    ->group(function (): void {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::get('/create', [ContentController::class, 'create'])->name('create');
        Route::post('/', [ContentController::class, 'store'])->name('store');
        Route::post('/bulk-delete', [ContentController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::get('/{entry}/edit', [ContentController::class, 'edit'])->name('edit');
        Route::put('/{entry}', [ContentController::class, 'update'])->name('update');
        Route::delete('/{entry}', [ContentController::class, 'destroy'])->name('destroy');
        Route::post('/{entry}/transition/{transition}', [ContentController::class, 'transition'])->name('transition');
    });
