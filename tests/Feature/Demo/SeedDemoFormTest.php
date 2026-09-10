<?php

use Baobab\Demo\Actions\SeedDemoForm;
use Baobab\Demo\Models\DemoContent;
use Baobab\Forms\Models\Form;
use Baobab\Users\Models\User;

/**
 * Spec 17 §4, §9 décision 1 ; spec 14 §8 — le formulaire de contact de
 * démonstration (M8 point 5, Pass D, suivi n° 284).
 *
 * Patron `SeedDemoContentTest` : ce que le seeder crée est marqué, il ne se
 * repose pas par-dessus lui-même, et les suites qu'il active sont celles que
 * la Pass E du point 6 a réellement câblées (email_notification,
 * acknowledgement, admin_notification) — jamais webhook ni captcha, qui
 * n'ont rien à relier sur une installation fraîche.
 */
beforeEach(function () {
    $this->acteur = User::create([
        'name' => 'Super Admin',
        'email' => 'admin-demo@exemple.fr',
        'password' => 'secret',
    ]);
});

it('crée le formulaire de contact avec ses champs et ses suites', function () {
    $lignes = app(SeedDemoForm::class)($this->acteur);

    $form = Form::where('slug', 'contact')->firstOrFail();
    $keys = collect($form->blueprint['fields'])->pluck('key')->all();

    expect($lignes)->toBe(['Formulaire de démonstration créé : Contact.'])
        ->and($keys)->toBe(['name', 'email', 'message', 'consent'])
        ->and($form->settings['suites']['email_notification']['enabled'])->toBeTrue()
        ->and($form->settings['suites']['email_notification']['recipients'])->toBe([$this->acteur->email])
        ->and($form->settings['suites']['acknowledgement']['enabled'])->toBeTrue()
        ->and($form->settings['suites']['admin_notification']['enabled'])->toBeTrue()
        ->and($form->settings['suites']['webhook']['enabled'])->toBeFalse()
        ->and($form->store_submissions)->toBeTrue();
});

it('marque le formulaire qu\'elle crée', function () {
    app(SeedDemoForm::class)($this->acteur);

    $form = Form::where('slug', 'contact')->firstOrFail();

    expect(DemoContent::where('demoable_type', Form::class)->where('demoable_id', $form->id)->exists())->toBeTrue();
});

it('ne repose rien si le formulaire de démonstration est déjà là', function () {
    app(SeedDemoForm::class)($this->acteur);

    $lignes = app(SeedDemoForm::class)($this->acteur);

    expect(Form::query()->count())->toBe(1)
        ->and($lignes)->toBe(['Le formulaire de démonstration est déjà en place.']);
});
