<?php

use Baobab\Forms\Http\Controllers\SubmitFormController;
use Illuminate\Support\Facades\Route;

/**
 * Endpoint public de soumission (spec 14 §4, M8 point 6 Pass C1). Liaison de
 * route implicite par slug (`{form:slug}`) plutôt que par identifiant : le
 * slug est déjà l'identifiant public du formulaire (export/import, Pass B4).
 */
Route::post('/baobab/forms/{form:slug}', SubmitFormController::class)
    ->middleware('throttle:baobab-forms-submit')
    ->name('baobab.forms.submit');
