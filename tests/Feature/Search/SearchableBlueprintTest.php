<?php

use Illuminate\Support\Facades\File;
use Laravel\Scout\Searchable;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('defaults searchableFields() to empty (opt-in, unlike exposed_in_api)', function () {
    [$contentType] = buildApiArticle();

    expect($contentType->searchableFields())->toBe([]);
});

it('returns only fields explicitly marked searchable, with their declared weight', function () {
    [$contentType] = buildApiArticle([
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true, 'weight' => 5],
            ['key' => 'price', 'type' => 'decimal'],
        ],
    ]);

    $searchable = $contentType->searchableFields();

    expect($searchable)->toHaveCount(1)
        ->and($searchable[0]['key'])->toBe('brand')
        ->and($contentType->fieldWeight($searchable[0]))->toBe(5)
        ->and($contentType->fieldWeight(['key' => 'price']))->toBe(1);
});

it('generates the Searchable trait and toSearchableArray() only when a field is searchable', function () {
    // Clé et forme de champs identiques à celles utilisées par
    // SearchSourcesTest/SearchCommandsTest pour cette même clé : une classe
    // PHP déjà chargée en mémoire dans ce process ne peut pas être
    // redéclarée avec un fillable différent, même après régénération du
    // fichier sur disque — toute divergence de forme sur une clé partagée
    // entre fichiers de test casse silencieusement l'un des deux côtés.
    [, $carClass] = buildApiArticle([
        'key' => 'SearchableArticle',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true]],
    ]);

    expect(class_uses_recursive($carClass))->toHaveKey(Searchable::class)
        ->and(method_exists($carClass, 'toSearchableArray'))->toBeTrue();

    $entry = $carClass::create(['brand' => 'Peugeot', 'slug' => 'peugeot', 'status' => 'published']);

    expect($entry->toSearchableArray())->toBe([
        'id' => $entry->id,
        'status' => 'published',
        'brand' => 'Peugeot',
    ]);
});

it('does not add Searchable/toSearchableArray() to a Content Type with no searchable field', function () {
    [, $carClass] = buildApiArticle();

    expect(class_uses_recursive($carClass))->not->toHaveKey(Searchable::class)
        ->and(method_exists($carClass, 'toSearchableArray'))->toBeFalse();
});
