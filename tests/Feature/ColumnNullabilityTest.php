<?php

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\ColumnNullability;

/**
 * Suivi n° 137 — la nullabilité d'une colonne dérive du drapeau `required` du
 * champ, et non du type. Ces cas balaient le catalogue par ses deux extrémités :
 * les types qui écrivaient NOT NULL en dur, ceux qui écrivaient ->nullable() en
 * dur, et les trois exceptions assumées.
 */
it('adds nullable to a definition that carries none when the field is optional', function (string $definition, string $expected) {
    expect(ColumnNullability::apply($definition, false))->toBe($expected);
})->with([
    ["\$table->string('brand', 255);", "\$table->string('brand', 255)->nullable();"],
    ["\$table->text('notes');", "\$table->text('notes')->nullable();"],
    ["\$table->longText('body');", "\$table->longText('body')->nullable();"],
    ["\$table->decimal('price', 10, 2);", "\$table->decimal('price', 10, 2)->nullable();"],
    ["\$table->unsignedBigInteger('mileage');", "\$table->unsignedBigInteger('mileage')->nullable();"],
]);

it('keeps a unique modifier when appending nullable', function () {
    // `slug` est le seul des six types fautifs à chaîner un modifieur : le
    // ->nullable() s'ajoute après lui, l'ordre des modifieurs étant indifférent
    // sur une ColumnDefinition.
    expect(ColumnNullability::apply("\$table->string('slug')->unique();", false))
        ->toBe("\$table->string('slug')->unique()->nullable();");
});

it('strips nullable from a definition that carries one when the field is required', function (string $definition, string $expected) {
    expect(ColumnNullability::apply($definition, true))->toBe($expected);
})->with([
    ["\$table->date('sold_on')->nullable();", "\$table->date('sold_on');"],
    ["\$table->dateTime('listed_at')->nullable();", "\$table->dateTime('listed_at');"],
    ["\$table->json('specs')->nullable();", "\$table->json('specs');"],
    ["\$table->string('status')->nullable();", "\$table->string('status');"],
]);

it('leaves a definition unchanged when it already matches the flag', function () {
    expect(ColumnNullability::apply("\$table->text('notes')->nullable();", false))
        ->toBe("\$table->text('notes')->nullable();")
        ->and(ColumnNullability::apply("\$table->text('notes');", true))
        ->toBe("\$table->text('notes');");
});

it('leaves a columnless field alone', function () {
    expect(ColumnNullability::apply('', true))->toBe('')
        ->and(ColumnNullability::apply('', false))->toBe('');
});

it('never touches a media foreign key, in either direction', function (bool $required) {
    // Abstention délibérée : ->constrained('media')->nullOnDelete() exige une
    // colonne qui accepte NULL, et ->nullable() ajouté en fin de chaîne
    // porterait sur la ForeignKeyDefinition, pas sur la colonne.
    $definition = "\$table->foreignId('cover')->nullable()->constrained('media')->nullOnDelete();";

    expect(ColumnNullability::apply($definition, $required))->toBe($definition);
})->with([true, false]);

it('makes every catalogue type honour the required flag', function () {
    $registry = app(FieldRegistry::class);

    // Les trois abstentions documentées : `boolean` porte un ->default(false)
    // qui répond déjà à l'absence de valeur, `file`/`image` sont des clés
    // étrangères vers media, `gallery` n'a pas de colonne.
    $abstentions = ['boolean', 'file', 'image', 'gallery'];

    foreach (array_keys($registry->all()) as $key) {
        if (in_array($key, $abstentions, true)) {
            continue;
        }

        $raw = $registry->resolve($key)->columnDefinition('sample', []);

        expect(ColumnNullability::apply($raw, false))->toContain('->nullable()')
            ->and(ColumnNullability::apply($raw, true))->not->toContain('->nullable()');
    }
});
