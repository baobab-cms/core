<?php

declare(strict_types=1);

use Baobab\Install\Http\Controllers\InstallController;
use Baobab\Install\Http\Controllers\WizardController;
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
|
| Les deux limiteurs sont **nommés** et non déclarés inline (`throttle:5,1`) :
| seul un limiteur nommé peut rendre sa propre réponse, et un 429 nu au milieu
| d'une installation laisse quelqu'un devant une page blanche sans savoir
| combien de temps attendre ni où retrouver son code. Leur définition vit dans
| `BaobabServiceProvider::registerInstallRateLimiters()`.
*/

Route::middleware(['web', EnsureNotInstalled::class])
    ->prefix('install')
    ->name('baobab.install.')
    ->group(function (): void {
        Route::middleware('throttle:baobab-install-gate')->group(function (): void {
            Route::get('/', [InstallController::class, 'gate'])->name('gate');
            Route::post('/', [InstallController::class, 'unlock'])->name('unlock');
        });

        /*
        | Le parcours (Pass C2b). Trois écrans de saisie qui remplissent un
        | brouillon en session, puis un écran de progression qui fait avancer
        | le pipeline **une étape par requête** (§6.1, n° 211).
        |
        | Rien ne s'écrit avant `run` : c'est ce qui rend le retour arrière
        | réel, et non un bouton qui ment.
        */
        Route::middleware([EnsureInstallSession::class, 'throttle:baobab-install-steps'])->group(function (): void {
            Route::get('/start', [WizardController::class, 'requirements'])->name('index');

            Route::get('/database', [WizardController::class, 'database'])->name('database');
            Route::post('/database', [WizardController::class, 'storeDatabase']);

            Route::get('/account', [WizardController::class, 'account'])->name('account');
            Route::post('/account', [WizardController::class, 'storeAccount']);

            Route::get('/site', [WizardController::class, 'site'])->name('site');
            Route::post('/site', [WizardController::class, 'storeSite']);

            Route::get('/run', [WizardController::class, 'run'])->name('run');
            Route::post('/step', [WizardController::class, 'step'])->name('step');
        });
    });
