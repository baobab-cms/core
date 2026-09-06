<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

/**
 * `<x-baobab::field.file>` (spec 14 §5, M8 point 6 Pass C3) — saisie native,
 * distincte de `field.media` (Content Types, sélectionne un média déjà
 * présent). `$errors` partagé manuellement, patron `ComponentsTest`.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

it('renders a native file input with its label', function () {
    $html = Blade::render('<x-baobab::field.file :name="$name" :label="$label" />', [
        'name' => 'cv',
        'label' => 'Curriculum vitae',
    ]);

    expect($html)->toContain('type="file"')
        ->toContain('name="cv"')
        ->toContain('Curriculum vitae');
});

it('never renders a value attribute — browsers refuse to prefill a file input', function () {
    $html = Blade::render('<x-baobab::field.file :name="$name" />', ['name' => 'cv']);

    expect($html)->not->toContain('value=');
});

it('propagates the required attribute like every other field component', function () {
    $html = Blade::render('<x-baobab::field.file :name="$name" required />', ['name' => 'cv']);

    expect($html)->toContain('required');
});
