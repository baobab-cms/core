<?php

declare(strict_types=1);

use Baobab\Api\Http\Controllers\ContentController;
use Baobab\Api\Http\Controllers\OpenApiSpecController;
use Baobab\Api\Http\Controllers\RolesController;
use Baobab\Api\Http\Controllers\SearchController;
use Baobab\Api\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('openapi.json', [OpenApiSpecController::class, 'show'])->name('openapi');
Route::get('search', [SearchController::class, 'index'])->name('search');

Route::get('content/{type}', [ContentController::class, 'index'])->name('content.index');
Route::post('content/{type}', [ContentController::class, 'store'])->name('content.store');
Route::get('content/{type}/{entry}', [ContentController::class, 'show'])->name('content.show');
Route::patch('content/{type}/{entry}', [ContentController::class, 'update'])->name('content.update');
Route::delete('content/{type}/{entry}', [ContentController::class, 'destroy'])->name('content.destroy');
Route::post('content/{type}/{entry}/publish', [ContentController::class, 'publish'])->name('content.publish');
Route::post('content/{type}/{entry}/restore', [ContentController::class, 'restore'])->name('content.restore');
Route::get('content/{type}/{entry}/revisions', [ContentController::class, 'revisions'])->name('content.revisions');

// Spec 05 §7, décision 5.
Route::get('users', [UsersController::class, 'index'])->name('users.index');
Route::post('users', [UsersController::class, 'store'])->name('users.store');
Route::get('users/{user}', [UsersController::class, 'show'])->name('users.show');
Route::patch('users/{user}', [UsersController::class, 'update'])->name('users.update');
Route::patch('me', [UsersController::class, 'updateMe'])->name('me.update');
Route::post('users/{user}/invitation', [UsersController::class, 'resendInvitation'])->name('users.invitation.resend');
Route::delete('users/{user}/invitation', [UsersController::class, 'cancelInvitation'])->name('users.invitation.cancel');
Route::post('users/{user}/roles', [UsersController::class, 'grantRole'])->name('users.roles.store');
Route::delete('users/{user}/roles/{role}', [UsersController::class, 'revokeRole'])->name('users.roles.destroy');
Route::get('roles', [RolesController::class, 'index'])->name('roles.index');

/**
 * Sans cette route, une requête `OPTIONS` ne correspond à aucune route
 * déclarée ci-dessus (méthode différente) : le routeur répond alors lui-même
 * en 200 avec un en-tête `Allow` généré automatiquement, sans jamais
 * traverser la pile de middlewares du groupe (`HandleApiCors` n'aurait donc
 * jamais l'occasion de répondre au préflight — bug découvert en écrivant les
 * tests Pass B). Ce fourre-tout fait matcher `OPTIONS` sur n'importe quel
 * sous-chemin pour que le groupe (et `HandleApiCors` en tête) s'exécute
 * réellement ; son corps n'est jamais atteint, `HandleApiCors` répond déjà
 * avant `$next()` pour toute requête `OPTIONS`.
 */
Route::options('{any}', fn () => response()->noContent())->where('any', '.*')->name('options');
