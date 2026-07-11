<?php

use Baobab\ContentTypes\Exceptions\UnknownFieldTypeException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Tests\Fixtures\DummyFieldTypeStub;

it('registers a field type and resolves it by key', function () {
    $registry = new FieldRegistry;
    $registry->register(DummyFieldTypeStub::class);

    expect($registry->has('dummy'))->toBeTrue()
        ->and($registry->resolve('dummy'))->toBeInstanceOf(DummyFieldTypeStub::class)
        ->and($registry->all())->toBe(['dummy' => DummyFieldTypeStub::class]);
});

it('reports an unregistered key as absent', function () {
    $registry = new FieldRegistry;

    expect($registry->has('dummy'))->toBeFalse();
});

it('throws when resolving an unregistered key', function () {
    $registry = new FieldRegistry;

    expect(fn () => $registry->resolve('dummy'))->toThrow(UnknownFieldTypeException::class);
});

it('resolves the 14 core field types registered at boot', function () {
    $registry = app(FieldRegistry::class);

    foreach ([
        'text', 'textarea', 'richtext', 'slug',
        'integer', 'decimal', 'boolean',
        'date', 'datetime', 'time',
        'select', 'multiselect', 'radio',
        'json',
    ] as $key) {
        expect($registry->has($key))->toBeTrue("Expected core field type [{$key}] to be registered.");
    }
});
