<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

/**
 * `<x-baobab::field.consent>` (spec 14 §2.2, M8 point 6 Pass C1) — seul champ
 * de formulaire dont le libellé porte un lien, ce que `field.checkbox`
 * (échappé, patron partagé avec les Content Types) ne permet pas.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});
it('renders the legal text without a privacy link when none is configured', function () {
    $html = Blade::render('<x-baobab::field.consent :name="$name" :text="$text" />', [
        'name' => 'gdpr',
        'text' => 'Accepter le traitement de mes donnees.',
    ]);

    expect($html)->toContain('Accepter le traitement de mes donnees.')
        ->not->toContain('<a ');
});

it('renders a link to the privacy policy when configured', function () {
    $html = Blade::render('<x-baobab::field.consent :name="$name" :text="$text" :privacy-url="$url" />', [
        'name' => 'gdpr',
        'text' => "J'accepte.",
        'url' => 'https://example.com/privacy',
    ]);

    expect($html)->toContain('href="https://example.com/privacy"')
        ->toContain(__('baobab::rendering.form_consent_privacy_link'));
});

it('escapes the legal text (never trusts admin-authored HTML as raw markup)', function () {
    $html = Blade::render('<x-baobab::field.consent :name="$name" :text="$text" />', [
        'name' => 'gdpr',
        'text' => '<script>alert(1)</script>',
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});
