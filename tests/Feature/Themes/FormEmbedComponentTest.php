<?php

use Baobab\Forms\Actions\SaveForm;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

/**
 * `<x-baobab::form-embed>` (spec 14 §4, M8 point 6 Pass C1/C2) — rendu public
 * par défaut, patron `FieldDisplayComponentsTest` (`Blade::render()`).
 * `$errors` partagé manuellement (patron `ComponentsTest`) : la vraie requête
 * HTTP le fait via `ShareErrorsFromSession` (`web`), absent de `Blade::render()`.
 */
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});
it('renders nothing for an unknown slug (patron EntryLink/Auto)', function () {
    expect(trim(Blade::render('<x-baobab::form-embed slug="does-not-exist" />')))->toBe('');
});

it('renders the CSRF token, the form fields and the submit action', function () {
    app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'full_name', 'type' => 'text', 'label' => 'Nom complet', 'required' => true],
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
        ],
    ]);

    $html = Blade::render('<x-baobab::form-embed slug="contact" />');

    expect($html)->toContain('name="_token"')
        ->toContain('name="full_name"')
        ->toContain('name="email"')
        ->toContain('required')
        ->toContain(route('baobab.forms.submit', ['form' => 'contact']))
        ->toContain(__('baobab::rendering.form_submit_action'));
});

it('does not mark optional fields as required', function () {
    app(SaveForm::class)(null, [
        'slug' => 'newsletter',
        'title' => 'Newsletter',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => false]],
    ]);

    expect(Blade::render('<x-baobab::form-embed slug="newsletter" />'))->not->toContain('required');
});

it('shows the flashed confirmation message instead of the form for the matching slug', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    session(['baobab_form_confirmation' => ['slug' => 'contact', 'message' => 'Merci !']]);

    $html = Blade::render('<x-baobab::form-embed slug="contact" />');

    expect($html)->toContain('Merci !')->not->toContain('<form');
});

it('keeps showing the form when the flashed confirmation belongs to a different form', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    session(['baobab_form_confirmation' => ['slug' => 'newsletter', 'message' => 'Merci !']]);

    $html = Blade::render('<x-baobab::form-embed slug="contact" />');

    expect($html)->not->toContain('Merci !')->toContain('<form');
});

it('defaults to redirect mode, never attaching the fragment listener', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    expect(Blade::render('<x-baobab::form-embed slug="contact" />'))->toContain("mode: 'redirect'");
});

it('passes the fragment mode through to the Alpine component', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    expect(Blade::render('<x-baobab::form-embed slug="contact" mode="fragment" />'))->toContain("mode: 'fragment'");
});

it('falls back to redirect mode for an unknown mode value, rather than trusting arbitrary input', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    expect(Blade::render('<x-baobab::form-embed slug="contact" mode="carrier-pigeon" />'))->toContain("mode: 'redirect'");
});

it('sets enctype=multipart/form-data when the form has a file field (Pass C3)', function () {
    app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'cv', 'type' => 'file']],
    ]);

    $html = Blade::render('<x-baobab::form-embed slug="contact" />');

    expect($html)->toContain('enctype="multipart/form-data"')
        ->toContain('type="file"')
        ->toContain('name="cv"');
});

it('omits enctype when the form has no file field', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'email', 'type' => 'email']]]);

    expect(Blade::render('<x-baobab::form-embed slug="contact" />'))->not->toContain('enctype');
});
