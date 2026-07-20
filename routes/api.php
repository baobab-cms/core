<?php

declare(strict_types=1);

use Baobab\Api\Http\Controllers\ContentController;
use Baobab\Api\Http\Controllers\OpenApiSpecController;
use Illuminate\Support\Facades\Route;

Route::get('openapi.json', [OpenApiSpecController::class, 'show'])->name('openapi');

Route::get('content/{type}', [ContentController::class, 'index'])->name('content.index');
Route::post('content/{type}', [ContentController::class, 'store'])->name('content.store');
Route::get('content/{type}/{entry}', [ContentController::class, 'show'])->name('content.show');
Route::patch('content/{type}/{entry}', [ContentController::class, 'update'])->name('content.update');
Route::delete('content/{type}/{entry}', [ContentController::class, 'destroy'])->name('content.destroy');
Route::post('content/{type}/{entry}/publish', [ContentController::class, 'publish'])->name('content.publish');
Route::post('content/{type}/{entry}/restore', [ContentController::class, 'restore'])->name('content.restore');
Route::get('content/{type}/{entry}/revisions', [ContentController::class, 'revisions'])->name('content.revisions');

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
