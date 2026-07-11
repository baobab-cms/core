<?php

use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationDefinitionGenerator;
use Baobab\Users\Models\User;

function makeContentType(string $key = 'Car', string $table = 'ct_cars'): ContentType
{
    return new ContentType([
        'key' => $key,
        'table_name' => $table,
        'is_addressable' => false,
        'version' => 1,
        'blueprint' => ['key' => $key, 'label' => ['singular' => $key, 'plural' => $key]],
    ]);
}

// ── one_to_one ───────────────────────────────────────────────────────────────

it('builds a unique nullable FK column and a belongsTo method for one_to_one', function () {
    $generator = new RelationDefinitionGenerator;
    $relation = ['key' => 'engine', 'type' => 'one_to_one', 'target' => 'Engine'];
    $target = ['class' => 'Modules\\Engine\\Models\\Engine', 'table' => 'ct_engines', 'key' => 'Engine'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('engine_id')->nullable()->unique()->constrained('ct_engines')->restrictOnDelete();");

    $method = $generator->eloquentMethod($relation, $target, makeContentType());
    expect($method)->toContain('public function engine(): \Illuminate\Database\Eloquent\Relations\BelongsTo')
        ->and($method)->toContain("return \$this->belongsTo(\\Modules\\Engine\\Models\\Engine::class, 'engine_id');");
});

// ── one_to_many ──────────────────────────────────────────────────────────────

it('builds a non-unique FK column and a belongsTo method for one_to_many', function () {
    $generator = new RelationDefinitionGenerator;
    $relation = ['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand'];
    $target = ['class' => 'Modules\\Brand\\Models\\Brand', 'table' => 'ct_brands', 'key' => 'Brand'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('brand_id')->nullable()->constrained('ct_brands')->restrictOnDelete();");

    $method = $generator->eloquentMethod($relation, $target, makeContentType());
    expect($method)->toContain('public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
});

it('honors on_delete cascade/set_null options', function () {
    $generator = new RelationDefinitionGenerator;
    $target = ['class' => 'Modules\\Brand\\Models\\Brand', 'table' => 'ct_brands', 'key' => 'Brand'];

    expect($generator->columnsDefinition(['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand', 'on_delete' => 'cascade'], $target))
        ->toContain('cascadeOnDelete()');

    expect($generator->columnsDefinition(['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand', 'on_delete' => 'set_null'], $target))
        ->toContain('nullOnDelete()');
});

// ── many_to_many ─────────────────────────────────────────────────────────────

it('adds no column but a belongsToMany method with an explicit alphabetical pivot name', function () {
    $generator = new RelationDefinitionGenerator;
    $relation = ['key' => 'options', 'type' => 'many_to_many', 'target' => 'Option'];
    $target = ['class' => 'Modules\\Option\\Models\\Option', 'table' => 'ct_options', 'key' => 'Option'];

    expect($generator->columnsDefinition($relation, $target))->toBe('');

    $method = $generator->eloquentMethod($relation, $target, makeContentType('Car', 'ct_cars'));
    expect($method)->toContain('public function options(): \Illuminate\Database\Eloquent\Relations\BelongsToMany')
        ->and($method)->toContain("return \$this->belongsToMany(\\Modules\\Option\\Models\\Option::class, 'ct_car_option');");
});

it('generates a pivot migration only for many_to_many', function () {
    $generator = new RelationDefinitionGenerator;
    $owner = makeContentType('Car', 'ct_cars');
    $target = ['class' => 'Modules\\Option\\Models\\Option', 'table' => 'ct_options', 'key' => 'Option'];

    $pivot = $generator->pivotMigration(['key' => 'options', 'type' => 'many_to_many', 'target' => 'Option'], $owner, $target);

    if ($pivot === null) {
        throw new RuntimeException('Expected a pivot migration to be generated for a many_to_many relation.');
    }

    expect($pivot['filename'])->toContain('_create_ct_car_option_table.php')
        ->and($pivot['contents'])->toContain("Schema::create('ct_car_option'")
        ->and($pivot['contents'])->toContain("\$table->foreignId('car_id')->constrained('ct_cars')->cascadeOnDelete();")
        ->and($pivot['contents'])->toContain("\$table->foreignId('option_id')->constrained('ct_options')->cascadeOnDelete();");

    $nonPivot = $generator->pivotMigration(['key' => 'brand', 'type' => 'one_to_many', 'target' => 'Brand'], $owner, $target);
    expect($nonPivot)->toBeNull();
});

// ── polymorphic ──────────────────────────────────────────────────────────────

it('builds morph columns and a morphTo method for polymorphic relations', function () {
    $generator = new RelationDefinitionGenerator;
    $relation = ['key' => 'commentable', 'type' => 'polymorphic', 'target' => 'Car'];
    $target = ['class' => 'Modules\\Car\\Models\\Car', 'table' => 'ct_cars', 'key' => 'Car'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->nullableMorphs('commentable');");

    $method = $generator->eloquentMethod($relation, $target, makeContentType('Comment', 'ct_comments'));
    expect($method)->toContain('public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo')
        ->and($method)->toContain('return $this->morphTo();');
});

it('resolves the User core model as a relation target', function () {
    $generator = new RelationDefinitionGenerator;
    $relation = ['key' => 'reviewer', 'type' => 'one_to_many', 'target' => 'User'];
    $target = ['class' => User::class, 'table' => 'users', 'key' => 'User'];

    expect($generator->columnsDefinition($relation, $target))
        ->toBe("            \$table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();");
});
