<?php

declare(strict_types=1);

use Baobab\Api\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('content/{type}', [ContentController::class, 'index'])->name('content.index');
Route::get('content/{type}/{entry}', [ContentController::class, 'show'])->name('content.show');
