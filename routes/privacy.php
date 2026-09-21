<?php

use Baobab\Privacy\Http\Controllers\DownloadExportController;
use Baobab\Privacy\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

/**
 * Téléchargement public d'une archive d'export de données personnelles (spec
 * 16 §4.1, M9 0.b Pass D1). Signée : le lien part par e-mail au sujet.
 * L'échéance n'est pas dans la signature mais sur la demande (410).
 */
Route::get('/baobab/privacy/exports/{privacyRequest:uuid}', DownloadExportController::class)
    ->middleware(['signed', 'throttle:baobab-privacy-download'])
    ->name('baobab.privacy.export.download');

/**
 * Portail public en libre-service (spec 16 §4, M9 0.b Pass E1). La saisie est
 * limitée par IP et par adresse ; les pages à lien signé partagent la limite
 * du téléchargement. Les signatures sont vérifiées par le contrôleur, qui
 * répond par un état lisible plutôt que par la page 403 brute. Le GET et le
 * POST d'une même page partagent leur URL signée : le GET n'a jamais d'effet
 * (préchargement des liens par les messageries), le POST seul agit.
 */
Route::get('/baobab/privacy', [PortalController::class, 'show'])->name('baobab.privacy.portal');
Route::post('/baobab/privacy', [PortalController::class, 'request'])
    ->middleware('throttle:baobab-privacy-portal')
    ->name('baobab.privacy.portal.request');

Route::middleware('throttle:baobab-privacy-download')->group(function (): void {
    Route::get('/baobab/privacy/verify', [PortalController::class, 'verify'])->name('baobab.privacy.portal.verify');
    Route::post('/baobab/privacy/verify', [PortalController::class, 'confirm'])->name('baobab.privacy.portal.confirm');

    Route::get('/baobab/privacy/delivery/{privacyRequest:uuid}', [PortalController::class, 'delivery'])->name('baobab.privacy.portal.delivery');
    Route::post('/baobab/privacy/delivery/{privacyRequest:uuid}', [PortalController::class, 'reveal'])->name('baobab.privacy.portal.reveal');
});
