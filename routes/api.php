<?php

declare(strict_types=1);

use Baobab\Api\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('content/{type}', [ContentController::class, 'index'])->name('content.index');
Route::post('content/{type}', [ContentController::class, 'store'])->name('content.store');
Route::get('content/{type}/{entry}', [ContentController::class, 'show'])->name('content.show');
Route::patch('content/{type}/{entry}', [ContentController::class, 'update'])->name('content.update');
Route::delete('content/{type}/{entry}', [ContentController::class, 'destroy'])->name('content.destroy');
Route::post('content/{type}/{entry}/publish', [ContentController::class, 'publish'])->name('content.publish');
Route::post('content/{type}/{entry}/restore', [ContentController::class, 'restore'])->name('content.restore');
Route::get('content/{type}/{entry}/revisions', [ContentController::class, 'revisions'])->name('content.revisions');
