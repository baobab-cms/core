<?php

use Baobab\Privacy\Http\Controllers\DownloadExportController;
use Illuminate\Support\Facades\Route;

/**
 * Téléchargement public d'une archive d'export de données personnelles (spec
 * 16 §4.1, M9 0.b Pass D1). Signée : le lien part par e-mail au sujet.
 * L'échéance n'est pas dans la signature mais sur la demande (410).
 */
Route::get('/baobab/privacy/exports/{privacyRequest:uuid}', DownloadExportController::class)
    ->middleware(['signed', 'throttle:baobab-privacy-download'])
    ->name('baobab.privacy.export.download');
