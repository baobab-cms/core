<?php

use Baobab\Studio\Generator\StudioRelationDefinitionGenerator;
use Baobab\Users\Models\User;

/**
 * Couvre les relations **telles qu'un Content Type les déclare** : préfixe
 * `ct_` sur les tables pivots, cibles déjà résolues.
 *
 * Depuis la Pass A du M8 point 2 (suivi n° 157), le sujet est
 * `StudioRelationDefinitionGenerator`, générateur unique : l'ancien
 * `Baobab\ContentTypes\Relations\RelationDefinitionGenerator` en était le
 * jumeau à un préfixe près, devenu un réglage (`withPivotPrefix()`). Les
 * assertions, elles, sont celles d'avant la fusion — c'est tout leur intérêt.
 */
function contentTypeRelations(): StudioRelationDefinitionGenerator
{
    return (new StudioRelationDefinitionGenerator)->withPivotPrefix('ct_');
}

// ── one_to_one ───────────────────────────────────────────────────────────────

it('builds a unique nullable FK column and a belongsTo method for one_to_one', function () {
    $generator = contentTypeRelations();
    $relation = ['key' => 'engine', 'type' => 'one_to_one', 'target' => 'content_type:Engine'];
    $target = ['class' => 'Modules\\Engine\\Models\\Engine', 'table' => 'ct_engines', 'key' => 'Engine'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('engine_id')->nullable()->unique()->constrained('ct_engines')->restrictOnDelete();");

    $method = $generator->eloquentMethod($relation, $target, 'Modules\\Car', 'Car');
    expect($method)->toContain('public function engine(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->and($method)->toContain("return \$this->belongsTo(\\Modules\\Engine\\Models\\Engine::class, 'engine_id');");
});

// ── one_to_many ──────────────────────────────────────────────────────────────

it('builds a non-unique FK column and a belongsTo method for one_to_many', function () {
    $generator = contentTypeRelations();
    $relation = ['key' => 'brand', 'type' => 'one_to_many', 'target' => 'content_type:Brand'];
    $target = ['class' => 'Modules\\Brand\\Models\\Brand', 'table' => 'ct_brands', 'key' => 'Brand'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('brand_id')->nullable()->constrained('ct_brands')->restrictOnDelete();");

    $method = $generator->eloquentMethod($relation, $target, 'Modules\\Car', 'Car');
    expect($method)->toContain('public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
});

it('honors on_delete cascade/set_null options', function () {
    $generator = contentTypeRelations();
    $target = ['class' => 'Modules\\Brand\\Models\\Brand', 'table' => 'ct_brands', 'key' => 'Brand'];

    expect($generator->columnsDefinition(['key' => 'brand', 'type' => 'one_to_many', 'target' => 'content_type:Brand', 'on_delete' => 'cascade'], $target))
        ->toContain('cascadeOnDelete()');

    expect($generator->columnsDefinition(['key' => 'brand', 'type' => 'one_to_many', 'target' => 'content_type:Brand', 'on_delete' => 'set_null'], $target))
        ->toContain('nullOnDelete()');
});

// ── many_to_many ─────────────────────────────────────────────────────────────

it('adds no column but a belongsToMany method with an explicit alphabetical pivot name', function () {
    $generator = contentTypeRelations();
    $relation = ['key' => 'options', 'type' => 'many_to_many', 'target' => 'content_type:Option'];
    $target = ['class' => 'Modules\\Option\\Models\\Option', 'table' => 'ct_options', 'key' => 'Option'];

    expect($generator->columnsDefinition($relation, $target))->toBe('');

    $method = $generator->eloquentMethod($relation, $target, 'Modules\\Car', 'Car');
    expect($method)->toContain('public function options(): \Illuminate\Database\Eloquent\Relations\BelongsToMany')
        ->and($method)->toContain("return \$this->belongsToMany(\\Modules\\Option\\Models\\Option::class, 'ct_car_option');");
});

it('generates a pivot migration only for many_to_many', function () {
    $generator = contentTypeRelations();
    $target = ['class' => 'Modules\\Option\\Models\\Option', 'table' => 'ct_options', 'key' => 'Option'];
    $moduleDir = generatedModulesPath().'/content-cars';

    $pivot = $generator->pivotMigration(
        ['key' => 'options', 'type' => 'many_to_many', 'target' => 'content_type:Option'],
        'Car',
        'ct_cars',
        $target,
        $moduleDir,
    );

    if ($pivot === null) {
        throw new RuntimeException('Expected a pivot migration to be generated for a many_to_many relation.');
    }

    expect($pivot['filename'])->toContain('_create_ct_car_option_table.php')
        ->and($pivot['contents'])->toContain("Schema::create('ct_car_option'")
        ->and($pivot['contents'])->toContain("\$table->foreignId('car_id')->constrained('ct_cars')->cascadeOnDelete();")
        ->and($pivot['contents'])->toContain("\$table->foreignId('option_id')->constrained('ct_options')->cascadeOnDelete();");

    $nonPivot = $generator->pivotMigration(
        ['key' => 'brand', 'type' => 'one_to_many', 'target' => 'content_type:Brand'],
        'Car',
        'ct_cars',
        $target,
        $moduleDir,
    );
    expect($nonPivot)->toBeNull();
});

// ── polymorphic ──────────────────────────────────────────────────────────────

it('builds morph columns and a morphTo method for polymorphic relations', function () {
    $generator = contentTypeRelations();
    $relation = ['key' => 'commentable', 'type' => 'polymorphic', 'target' => 'content_type:Car'];
    $target = ['class' => 'Modules\\Car\\Models\\Car', 'table' => 'ct_cars', 'key' => 'Car'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->nullableMorphs('commentable');");

    $method = $generator->eloquentMethod($relation, $target, 'Modules\\Comment', 'Comment');
    expect($method)->toContain('public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo')
        ->and($method)->toContain('return $this->morphTo();');
});

it('resolves the User core model as a relation target', function () {
    $generator = contentTypeRelations();
    $relation = ['key' => 'reviewer', 'type' => 'one_to_many', 'target' => 'core:User'];
    $target = ['class' => User::class, 'table' => 'users', 'key' => 'User'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();");
});

// ── le préfixe est bien un réglage, pas une constante ────────────────────────

it('leaves pivot tables unprefixed for a plain Studio module', function () {
    $generator = new StudioRelationDefinitionGenerator;
    $relation = ['key' => 'options', 'type' => 'many_to_many', 'target' => 'entity:Option'];
    $target = ['class' => null, 'table' => 'options', 'key' => 'Option'];

    expect($generator->eloquentMethod($relation, $target, 'Garage\\Fleet', 'Car'))
        ->toContain("'car_option'")
        ->not->toContain('ct_car_option');
});
