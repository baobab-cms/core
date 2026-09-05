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

it('rejects options that fail the field type optionsRules — a select without choices', function () {
    expect(fn () => FormBlueprint::fromArray([
        ['key' => 'topic', 'type' => 'select', 'options' => []],
    ]))->toThrow(InvalidFormBlueprintException::class);
});
