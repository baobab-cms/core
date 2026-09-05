<?php

use Baobab\Forms\Support\FormFieldsNormalizer;

it('normalizes a plain field, dropping empty label/placeholder/help_text', function () {
    $normalized = FormFieldsNormalizer::normalize([
        ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => '', 'help_text' => null, 'required' => true],
    ]);

    expect($normalized)->toBe([
        ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => null, 'help_text' => null, 'required' => true],
    ]);
});

it('turns options.choices into a clean list for select/radio/checkboxes, dropping blanks', function () {
    $normalized = FormFieldsNormalizer::normalize([
        ['key' => 'topic', 'type' => 'select', 'options' => ['choices' => [' support ', '', 'sales']]],
    ]);

    expect($normalized[0]['options'])->toBe(['choices' => ['support', 'sales']]);
});

it('keeps only text/privacy_url for a consent field, in options', function () {
    $normalized = FormFieldsNormalizer::normalize([
        ['key' => 'gdpr', 'type' => 'consent', 'options' => ['text' => " J'accepte. ", 'privacy_url' => 'https://example.com/privacy', 'choices' => ['ignored']]],
    ]);

    expect($normalized[0]['options'])->toBe(['text' => "J'accepte.", 'privacy_url' => 'https://example.com/privacy']);
});

it('omits options entirely for a type that needs none', function () {
    $normalized = FormFieldsNormalizer::normalize([
        ['key' => 'full_name', 'type' => 'text'],
    ]);

    expect($normalized[0])->not->toHaveKey('options');
});

it('ignores non-array entries and a non-array input', function () {
    expect(FormFieldsNormalizer::normalize(['not-an-array', ['key' => 'a', 'type' => 'text']]))->toHaveCount(1)
        ->and(FormFieldsNormalizer::normalize('not-an-array'))->toBe([]);
});
