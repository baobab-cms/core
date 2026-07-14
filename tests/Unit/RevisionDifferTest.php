<?php

use Baobab\ContentTypes\Editorial\Support\RevisionDiffer;

it('marks unchanged fields as unchanged, with no word diff', function () {
    $diff = (new RevisionDiffer)->diff(['title' => 'Renault'], ['title' => 'Renault']);

    expect($diff['title']['changed'])->toBeFalse()
        ->and($diff['title']['words'])->toBeNull();
});

it('produces a word-level diff for two changed strings', function () {
    $diff = (new RevisionDiffer)->diff(
        ['body' => 'the quick brown fox'],
        ['body' => 'the quick red fox jumps'],
    );

    expect($diff['body']['changed'])->toBeTrue();

    $words = $diff['body']['words'];
    $types = collect($words)->pluck('type')->all();

    expect($types)->toContain('equal')
        ->toContain('delete')
        ->toContain('insert');

    // "brown" disparaît, "red" et "jumps" apparaissent — le reste est commun.
    expect(collect($words)->firstWhere('text', 'brown')['type'])->toBe('delete')
        ->and(collect($words)->firstWhere('text', 'red')['type'])->toBe('insert')
        ->and(collect($words)->firstWhere('text', 'jumps')['type'])->toBe('insert');
});

it('falls back to before/after for non-string values', function () {
    $diff = (new RevisionDiffer)->diff(
        ['tags' => ['a', 'b']],
        ['tags' => ['a', 'c']],
    );

    expect($diff['tags']['changed'])->toBeTrue()
        ->and($diff['tags']['words'])->toBeNull()
        ->and($diff['tags']['before'])->toBe(['a', 'b'])
        ->and($diff['tags']['after'])->toBe(['a', 'c']);
});

it('includes keys present only on one side', function () {
    $diff = (new RevisionDiffer)->diff(['old_only' => 'x'], ['new_only' => 'y']);

    expect($diff['old_only'])->toBe(['before' => 'x', 'after' => null, 'changed' => true, 'words' => null])
        ->and($diff['new_only'])->toBe(['before' => null, 'after' => 'y', 'changed' => true, 'words' => null]);
});
