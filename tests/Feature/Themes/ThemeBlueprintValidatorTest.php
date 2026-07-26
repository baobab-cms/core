<?php

use Baobab\Themes\Blueprint\ThemeBlueprintValidator;
use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;

function themeBlueprintJson(array $overrides = []): string
{
    return (string) json_encode(array_replace([
        'name' => 'Acme Theme',
        'slug' => 'acme-theme',
    ], $overrides));
}

it('accepts a minimal valid blueprint', function () {
    app(ThemeBlueprintValidator::class)->validate(themeBlueprintJson());
})->throwsNoExceptions();

it('accepts a blueprint using every documented property', function () {
    app(ThemeBlueprintValidator::class)->validate(themeBlueprintJson([
        'version' => '2.1.0',
        'screenshot' => 'preview.png',
        'menus' => ['primary' => 'Navigation principale'],
        'widget_zones' => ['sidebar' => 'Barre latérale'],
        'supports' => ['search', 'forms'],
        'tokens' => ['colors' => ['primary' => '#123456']],
        'fonts' => [['family' => 'Acme Sans', 'files' => ['400' => 'acme-sans-400.woff2']]],
        'content_types' => ['Article' => ['templates' => ['show', 'index']]],
    ]));
})->throwsNoExceptions();

it('rejects malformed JSON', function () {
    app(ThemeBlueprintValidator::class)->validate('{not json');
})->throws(InvalidThemeBlueprintException::class);

it('rejects a blueprint missing the required name', function () {
    $json = (string) json_encode(['slug' => 'acme-theme']);

    expect(fn () => app(ThemeBlueprintValidator::class)->validate($json))
        ->toThrow(InvalidThemeBlueprintException::class);
});

it('rejects a blueprint missing the required slug', function () {
    $json = (string) json_encode(['name' => 'Acme Theme']);

    expect(fn () => app(ThemeBlueprintValidator::class)->validate($json))
        ->toThrow(InvalidThemeBlueprintException::class);
});

it('rejects a slug that is not kebab-case', function () {
    expect(fn () => app(ThemeBlueprintValidator::class)->validate(themeBlueprintJson(['slug' => 'Acme_Theme'])))
        ->toThrow(InvalidThemeBlueprintException::class);
});

it('rejects an unsupported capability in supports', function () {
    expect(fn () => app(ThemeBlueprintValidator::class)->validate(themeBlueprintJson(['supports' => ['not-a-real-capability']])))
        ->toThrow(InvalidThemeBlueprintException::class);
});

it('rejects an unknown top-level property', function () {
    expect(fn () => app(ThemeBlueprintValidator::class)->validate(themeBlueprintJson(['unexpected' => 'value'])))
        ->toThrow(InvalidThemeBlueprintException::class);
});

it('exposes formatted errors on the exception', function () {
    try {
        app(ThemeBlueprintValidator::class)->validate((string) json_encode(['slug' => 'acme-theme']));
        expect(false)->toBeTrue('Expected an exception to be thrown.');
    } catch (InvalidThemeBlueprintException $e) {
        expect($e->errors())->not->toBeEmpty()
            ->and($e->getMessage())->toContain('Blueprint de thème invalide');
    }
});
