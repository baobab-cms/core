<?php

use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;

it('submits a form, persists it and flashes the default confirmation message', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'full_name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'type' => 'email', 'required' => true],
        ],
    ]);

    $response = $this->post(route('baobab.forms.submit', ['form' => $form->slug]), [
        '_form_slug' => 'contact',
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('baobab_form_confirmation', [
            'slug' => 'contact',
            'message' => __('baobab::rendering.form_confirmation_default'),
        ]);

    expect(FormSubmission::count())->toBe(1)
        ->and(FormSubmission::first()->payload)->toBe(['full_name' => 'Jane Doe', 'email' => 'jane@example.com']);
});

it('flashes the configured confirmation message when the form sets one', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => ['confirmation' => ['message' => 'Merci, on revient vers vous sous 48h.']],
    ]);

    $this->post(route('baobab.forms.submit', ['form' => $form->slug]), ['email' => 'jane@example.com'])
        ->assertSessionHas('baobab_form_confirmation', [
            'slug' => 'contact',
            'message' => 'Merci, on revient vers vous sous 48h.',
        ]);
});

it('redirects back with validation errors and persists nothing on a rejected submission', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $this->from('/contact-page')
        ->post(route('baobab.forms.submit', ['form' => $form->slug]), [])
        ->assertRedirect('/contact-page')
        ->assertSessionHasErrors('email');

    expect(FormSubmission::count())->toBe(0);
});

it('never leaks the CSRF token or the form-scoping field into the stored payload', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $this->post(route('baobab.forms.submit', ['form' => $form->slug]), [
        '_form_slug' => 'contact',
        'email' => 'jane@example.com',
    ]);

    expect(FormSubmission::first()->payload)->toBe(['email' => 'jane@example.com']);
});

it('returns a 404 for an unknown form slug', function () {
    $this->post(route('baobab.forms.submit', ['form' => 'does-not-exist']))->assertNotFound();
});

it('returns the rendered confirmation as a fragment when X-Baobab-Form-Fragment is set', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $response = $this->post(
        route('baobab.forms.submit', ['form' => $form->slug]),
        ['email' => 'jane@example.com'],
        ['X-Baobab-Form-Fragment' => '1'],
    );

    $response->assertOk()
        ->assertSee(__('baobab::rendering.form_confirmation_default'))
        ->assertDontSee('<form', false);

    expect(FormSubmission::count())->toBe(1);
});

it('returns the re-rendered form with inline errors and a 422 in fragment mode, instead of redirecting', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'full_name', 'type' => 'text', 'required' => false],
            ['key' => 'email', 'type' => 'email', 'required' => true],
        ],
    ]);

    $response = $this->post(
        route('baobab.forms.submit', ['form' => $form->slug]),
        ['full_name' => 'Jane Doe'],
        ['X-Baobab-Form-Fragment' => '1'],
    );

    $response->assertStatus(422)
        ->assertSee('<form', false)
        ->assertSee('value="Jane Doe"', false);

    expect(FormSubmission::count())->toBe(0);
});

it('ignores a fragment header that is not exactly "1"', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->post(
        route('baobab.forms.submit', ['form' => $form->slug]),
        [],
        ['X-Baobab-Form-Fragment' => 'true'],
    )->assertRedirect();
});

it('throttles submissions by IP (baobab-forms-submit, 10/min)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [],
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('baobab.forms.submit', ['form' => $form->slug]))->assertRedirect();
    }

    $this->post(route('baobab.forms.submit', ['form' => $form->slug]))->assertStatus(429);
});
