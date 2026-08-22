<?php

use Baobab\Mail\Support\MailTemplateVariables;

it('normalises the short and long declaration forms alike', function () {
    $variables = new MailTemplateVariables([
        'user.email' => 'E-mail du destinataire',
        'reset_url' => ['label' => 'Lien de réinitialisation', 'required' => true],
        'user.name' => ['label' => 'Nom du destinataire'],
    ]);

    expect($variables->labels())->toBe([
        'user.email' => 'E-mail du destinataire',
        'reset_url' => 'Lien de réinitialisation',
        'user.name' => 'Nom du destinataire',
    ])
        ->and($variables->names())->toBe(['user.email', 'reset_url', 'user.name'])
        ->and($variables->required())->toBe(['reset_url']);
});

it('names every missing required variable, not just the first', function () {
    $variables = new MailTemplateVariables([
        'reset_url' => ['label' => 'Lien', 'required' => true],
        'expires_at' => ['label' => 'Expiration', 'required' => true],
        'user.name' => 'Nom',
    ]);

    expect($variables->missingRequiredIn(['user.name']))->toBe(['reset_url', 'expires_at'])
        ->and($variables->missingRequiredIn(['reset_url', 'expires_at']))->toBe([]);
});

it('spots placeholders the template never declared', function () {
    $variables = new MailTemplateVariables(['user.name' => 'Nom']);

    expect($variables->unknownIn(['user.name', 'account.balance']))->toBe(['account.balance'])
        ->and($variables->unknownIn(['user.name']))->toBe([]);
});

it('treats a long-form variable without required as optional', function () {
    $variables = new MailTemplateVariables(['user.name' => ['label' => 'Nom', 'required' => false]]);

    expect($variables->required())->toBe([]);
});
