<?php

use Baobab\ContentTypes\Evolution\BlueprintDiffer;

it('detects an added field', function () {
    $diff = (new BlueprintDiffer)->diff(
        [['key' => 'brand', 'type' => 'text']],
        [['key' => 'brand', 'type' => 'text'], ['key' => 'mileage', 'type' => 'integer']],
    );

    expect($diff['added'])->toBe([['key' => 'mileage', 'type' => 'integer']])
        ->and($diff['removed'])->toBe([])
        ->and($diff['renamed'])->toBe([])
        ->and($diff['type_changed'])->toBe([]);
});

it('detects a removed field', function () {
    $diff = (new BlueprintDiffer)->diff(
        [['key' => 'brand', 'type' => 'text'], ['key' => 'mileage', 'type' => 'integer']],
        [['key' => 'brand', 'type' => 'text']],
    );

    expect($diff['removed'])->toBe([['key' => 'mileage', 'type' => 'integer']])
        ->and($diff['added'])->toBe([]);
});

it('detects a renamed field via renamed_from, not as remove+add', function () {
    $diff = (new BlueprintDiffer)->diff(
        [['key' => 'brand', 'type' => 'text']],
        [['key' => 'make', 'type' => 'text', 'renamed_from' => 'brand']],
    );

    expect($diff['renamed'])->toBe([['from' => 'brand', 'to' => ['key' => 'make', 'type' => 'text', 'renamed_from' => 'brand']]])
        ->and($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([]);
});

it('detects a type change on a field with the same key', function () {
    $diff = (new BlueprintDiffer)->diff(
        [['key' => 'notes', 'type' => 'text']],
        [['key' => 'notes', 'type' => 'textarea']],
    );

    expect($diff['type_changed'])->toBe([
        [
            'key' => 'notes',
            'from_type' => 'text',
            'from' => ['key' => 'notes', 'type' => 'text'],
            'to' => ['key' => 'notes', 'type' => 'textarea'],
        ],
    ])
        ->and($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([]);
});

it('reports nothing for an unchanged field', function () {
    $field = ['key' => 'brand', 'type' => 'text'];

    $diff = (new BlueprintDiffer)->diff([$field], [$field]);

    expect($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([])
        ->and($diff['renamed'])->toBe([])
        ->and($diff['type_changed'])->toBe([]);
});

it('handles a mixed diff: one add, one remove, one rename, one type change', function () {
    $diff = (new BlueprintDiffer)->diff(
        [
            ['key' => 'brand', 'type' => 'text'],
            ['key' => 'obsolete', 'type' => 'text'],
            ['key' => 'notes', 'type' => 'text'],
        ],
        [
            ['key' => 'make', 'type' => 'text', 'renamed_from' => 'brand'],
            ['key' => 'notes', 'type' => 'textarea'],
            ['key' => 'mileage', 'type' => 'integer'],
        ],
    );

    expect($diff['added'])->toBe([['key' => 'mileage', 'type' => 'integer']])
        ->and($diff['removed'])->toBe([['key' => 'obsolete', 'type' => 'text']])
        ->and($diff['renamed'])->toBe([['from' => 'brand', 'to' => ['key' => 'make', 'type' => 'text', 'renamed_from' => 'brand']]])
        ->and($diff['type_changed'])->toBe([
            [
                'key' => 'notes',
                'from_type' => 'text',
                'from' => ['key' => 'notes', 'type' => 'text'],
                'to' => ['key' => 'notes', 'type' => 'textarea'],
            ],
        ]);
});
