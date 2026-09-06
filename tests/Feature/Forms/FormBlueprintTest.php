<?php

use Baobab\Forms\Blueprint\FormBlueprint;
use Baobab\Forms\Exceptions\InvalidFormBlueprintException;

it('accepts fields from the whitelist opened to forms by spec 14 §2.2', function () {
    $blueprint = FormBlueprint::fromArray([
        ['key' => 'full_name', 'type' => 'text', 'required' => true],
        ['key' => 'email', 'type' => 'email', 'required' => true],
        ['key' => 'topic', 'type' => 'select', 'options' => ['choices' => ['support', 'sales']]],
    ]);

    expect($blueprint->fields())->toHaveCount(3)
        ->and($blueprint->fields()[0]['key'])->toBe('full_name')
        ->and($blueprint->fields()[1]['type'])->toBe('email');
});

it('rejects a field type outside the catalogue subset opened to forms', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'body', 'type' => 'richtext'],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('rejects a manually declared honeypot — it is injected automatically, never authored', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'website', 'type' => 'honeypot'],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('rejects a duplicate field key', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'email', 'type' => 'email'],
        ['key' => 'email', 'type' => 'text'],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('accepts a consent field with its own options and rejects a missing legal text', function () {
    $blueprint = FormBlueprint::fromArray([
        ['key' => 'gdpr', 'type' => 'consent', 'required' => true, 'options' => [
            'text' => 'J\'accepte la politique de confidentialité.',
            'privacy_url' => 'https://example.com/privacy',
        ]],
    ]);

    expect($blueprint->fields()[0]['type'])->toBe('consent');

    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'gdpr', 'type' => 'consent', 'options' => []],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('keeps label/placeholder/help_text, and drops them when blank (spec 14 §2.2)', function () {
    $blueprint = FormBlueprint::fromArray([
        ['key' => 'full_name', 'type' => 'text', 'label' => 'Nom complet', 'placeholder' => 'Jane Doe', 'help_text' => 'Prénom et nom.'],
        ['key' => 'email', 'type' => 'email', 'label' => '', 'placeholder' => null],
    ]);

    expect($blueprint->fields()[0])->toMatchArray([
        'label' => 'Nom complet',
        'placeholder' => 'Jane Doe',
        'help_text' => 'Prénom et nom.',
    ])->and($blueprint->fields()[1])->toMatchArray([
        'label' => null,
        'placeholder' => null,
        'help_text' => null,
    ]);
});

it('rejects options that fail the field type optionsRules — a select without choices', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'topic', 'type' => 'select', 'options' => []],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('accepts a file field, like consent treated outside the FieldRegistry (spec 14 §5, Pass C3)', function () {
    $blueprint = FormBlueprint::fromArray([
        ['key' => 'cv', 'type' => 'file', 'required' => true],
    ]);

    expect($blueprint->fields()[0]['type'])->toBe('file');
});

it('accepts a file field with mime_types/max_size overrides, resserring the global defaults', function () {
    $blueprint = FormBlueprint::fromArray([
        ['key' => 'cv', 'type' => 'file', 'options' => ['mime_types' => ['application/pdf'], 'max_size' => 1_048_576]],
    ]);

    expect($blueprint->fields()[0]['options'])->toBe(['mime_types' => ['application/pdf'], 'max_size' => 1_048_576]);
});

it('rejects a file field whose mime_types option is not a list of strings', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'cv', 'type' => 'file', 'options' => ['mime_types' => 'application/pdf']],
    ]))->toThrow(InvalidFormBlueprintException::class);
});

it('rejects a file field whose max_size option is not a positive integer', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'cv', 'type' => 'file', 'options' => ['max_size' => 0]],
    ]))->toThrow(InvalidFormBlueprintException::class);
});
