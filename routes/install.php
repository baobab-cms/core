<?php

declare(strict_types=1);

use Baobab\Install\Http\Controllers\InstallController;
use Baobab\Install\Http\Middleware\EnsureInstallSession;
use Baobab\Install\Http\Middleware\EnsureNotInstalled;
use Illuminate\Support\Facades\Route;

/*
| Routes du wizard graphique — spec 15 §6.1, Pass C1.
|
| Chargées **conditionnellement** par le provider : elles n'existent qu'en
| l'absence de lock (§6.3). Le middleware `EnsureNotInstalled` les garde une
| seconde fois, pour l'intervalle que « au chargement suivant » laisse ouvert.
|
| **Throttling agressif**, comme le §6.2 l'exige, et à deux régimes parce que
| les deux surfaces ne courent pas le même risque : la porte accepte 5 essais
| par minute — c'est un secret de 128 bits qu'on y devine, et cinq tentatives
| suffisent largement à quelqu'un qui recopie un code à la main ; les écrans
| internes tolèrent 60 requêtes par minute, la Pass C2 devant y faire défiler
| une étape par requête (n° 211) sans se faire arrêter par sa propre garde.
*/

Route::middleware(['web', EnsureNotInstalled::class])
    ->prefix('install')
    ->name('baobab.install.')
    ->group(function (): void {
        Route::middleware('throttle:5,1')->group(function (): void {
            Route::get('/', [InstallController::class, 'gate'])->name('gate');
            Route::post('/', [InstallController::class, 'unlock'])->name('unlock');
        });

        Route::middleware([EnsureInstallSession::class, 'throttle:60,1'])->group(function (): void {
            Route::get('/start', [InstallController::class, 'index'])->name('index');
        });
    });
