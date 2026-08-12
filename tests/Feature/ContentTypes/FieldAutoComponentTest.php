<?php

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Doc\Models\Doc;

uses(RefreshDatabase::class);

require_once __DIR__.'/../../Fixtures/ModuleDocModel.php';

/**
 * `<x-baobab::field.auto>` et `<x-baobab::entry-author>` (spec 19 §5.4, §5.10)
 * sur un vrai couple Content Type / modèle : c'est la convention de nommage
 * `Modules\{Key}\Models\{Key}` qui relie l'instance à son blueprint.
 */
function docContentType(array $blueprint = []): ContentType
{
    return ContentType::create([
        'key' => 'Doc',
        'table_name' => 'ct_docs',
        'is_addressable' => true,
        'version' => 1,
        'blueprint' => array_replace([
            'key' => 'Doc',
            'label' => ['singular' => 'Doc', 'plural' => 'Docs'],
            'title_field' => 'title',
            'fields' => [
                ['key' => 'title', 'type' => 'text'],
                ['key' => 'body', 'type' => 'richtext'],
                ['key' => 'reference', 'type' => 'text', 'label' => 'Référence interne'],
                ['key' => 'secret', 'type' => 'text', 'exposed_in_api' => false],
            ],
        ], $blueprint),
    ]);
}

it('renders the body role through the richtext display component', function () {
    docContentType();

    $entry = new Doc(['title' => 'Titre', 'body' => '<p>Prose</p>']);

    $html = Blade::render('<x-baobab::field.auto :entry="$entry" role="body" />', ['entry' => $entry]);

    // Le composant richtext rend du HTML non échappé : c'est bien le
    // composant du catalogue qui a servi, pas un `{{ }}` du thème.
    expect($html)->toContain('<p>Prose</p>');
});

it('labels rest fields and never exposes a field withdrawn from the API', function () {
    docContentType();

    $entry = new Doc([
        'title' => 'Titre',
        'body' => '<p>Prose</p>',
        'reference' => 'REF-42',
        'secret' => 'ne doit pas fuiter',
    ]);

    $html = Blade::render('<x-baobab::field.auto :entry="$entry" />', ['entry' => $entry]);

    expect($html)->toContain('Référence interne')
        ->and($html)->toContain('REF-42')
        ->and($html)->not->toContain('ne doit pas fuiter')
        ->and($html)->not->toContain('Prose');
});

it('renders nothing for a role the type has no field for', function () {
    docContentType();

    $html = Blade::render('<x-baobab::field.auto :entry="$entry" role="image" />', ['entry' => new Doc(['title' => 'Titre'])]);

    expect(trim($html))->toBe('');
});

it('renders nothing at all for an entry belonging to no Content Type', function () {
    $html = Blade::render('<x-baobab::field.auto :entry="$entry" />', ['entry' => new User(['name' => 'Ada'])]);

    expect(trim($html))->toBe('');
});

it('resolves the author from the convention column, without a generated relation', function () {
    $author = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'secret-password']);

    $entry = new Doc(['title' => 'Titre', 'author_id' => $author->id]);

    $html = Blade::render('<x-baobab::entry-author :entry="$entry" />', ['entry' => $entry]);

    expect($html)->toContain('Ada Lovelace');
});

it('renders no author mention when the entry has none', function () {
    $html = Blade::render('<x-baobab::entry-author :entry="$entry" />', ['entry' => new Doc(['title' => 'Titre'])]);

    expect(trim($html))->toBe('');
});
