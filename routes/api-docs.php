<?php

use Baobab\Api\Http\Controllers\ApiDocsController;
use Baobab\Api\Http\Middleware\EnsureApiDocsEnabled;
use Illuminate\Support\Facades\Route;

/**
 * `/api/docs` (spec 08 §7, M7 point 4b Pass B) — hors du groupe `/api/v1/*`
 * (routes/api.php) : une page HTML consultée dans un navigateur, pas un
 * endpoint JSON pour un client API (donc pas de CORS/rate-limit/`EnsureApiEnabled`).
 */
Route::middleware(EnsureApiDocsEnabled::class)->get('api/docs', [ApiDocsController::class, 'show'])->name('api.docs');
