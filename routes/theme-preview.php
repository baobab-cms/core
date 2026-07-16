<?php

use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Route;

/**
 * Entrée/sortie de préview de thème (spec 03 §7, M6 point 2) — la session ne
 * concerne que le visiteur qui l'a ouverte, jamais les autres (spec :
 * « pour la session de l'administrateur uniquement »).
 */
// La route "stop" doit être déclarée avant {module} : sinon "stop" matche
// le paramètre {module} en premier (même forme d'URI, /theme-preview/{X}).
Route::get('/theme-preview/stop', function () {
    session()->forget('baobab.preview_theme_id');

    return redirect('/');
})->name('baobab.theme-preview.stop');

Route::get('/theme-preview/{module}', function (Module $module) {
    session(['baobab.preview_theme_id' => $module->id]);

    return redirect('/');
})->middleware('signed')->name('baobab.theme-preview.enter');
