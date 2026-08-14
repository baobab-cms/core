<?php

use Baobab\Modules\Models\ModulePermission;
use Baobab\Studio\Actions\GenerateModuleFromDraft;
use Baobab\Studio\Exceptions\ColumnHasNullsException;
use Baobab\Studio\Exceptions\DestructiveSchemaChangeNotConfirmedException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi n° 111 (qui porte le n° 137) — faire évoluer le schéma d'un module déjà
 * installé. Ces cas partent tous du même point : un module réellement généré,
 * installé et migré, dont on rouvre le blueprint.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Un nom de module et un nom de table **par test**.
 *
 * Ces cas installent délibérément des schémas différents, et une table n'a
 * qu'une migration de création dont le nom est déterministe depuis le n° 120 :
 * sous un nom de module partagé, le premier schéma installé dans le processus
 * fixerait la table pour tous les suivants. Même parade que `studioRegenDraft()`,
 * et pour la même raison.
 */
function evolutionSuffix(bool $next = false): string
{
    static $counter = 0;

    if ($next) {
        $counter++;
    }

    return (string) $counter;
}

/**
 * @param  list<array<string, mixed>>  $entities
 * @return array<string, mixed>
 */
function evolutionBlueprint(array $entities): array
{
    $suffix = evolutionSuffix();

    return [
        'blueprint_version' => 1,
        'identity' => ['name' => "garage/evolving{$suffix}", 'title' => 'Evolving', 'version' => '1.0.0', 'type' => 'module'],
        'entities' => $entities,
        'permissions' => ['auto_crud' => true, 'custom' => []],
    ];
}

/**
 * @param  list<array<string, mixed>>  $fields
 * @return array<string, mixed>
 */
function evolutionCarEntity(array $fields): array
{
    return [
        'key' => 'Car',
        'table' => evolutionTable(),
        'fields' => $fields,
        'relations' => [],
        'routes' => ['admin' => true, 'front' => false, 'api' => false],
    ];
}

function evolutionTable(string $entity = 'cars'): string
{
    return "evo{$entity}".evolutionSuffix();
}

function evolutionModuleDirectory(): string
{
    return generatedModulesPath().'/garage-evolving'.evolutionSuffix();
}

/**
 * Module généré, installé, activé et migré — le point de départ de toute
 * évolution. Retourne le brouillon rafraîchi, donc porteur de son instantané.
 *
 * @param  list<array<string, mixed>>  $fields
 */
function evolutionInstalledDraft(array $fields): ModuleBlueprintDraft
{
    $suffix = evolutionSuffix(next: true);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => "garage/evolving{$suffix}",
        'title' => 'Evolving',
        'current_step' => 9,
        'blueprint' => evolutionBlueprint([evolutionCarEntity($fields)]),
    ]);

    app(GenerateModuleFromDraft::class)($draft);

    return $draft->fresh() ?? $draft;
}

/**
 * @param  array<string, mixed>  $blueprint
 */
function evolutionRegenerate(ModuleBlueprintDraft $draft, array $blueprint, bool $confirmDestructive = false): array
{
    $draft->blueprint = $blueprint;
    $draft->save();

    return app(GenerateModuleFromDraft::class)($draft, [], $confirmDestructive);
}

it('creates the table of an entity added after installation', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    expect(Schema::hasTable(evolutionTable()))->toBeTrue()
        ->and(Schema::hasTable(evolutionTable('garages')))->toBeFalse();

    $result = evolutionRegenerate($draft, evolutionBlueprint([
        evolutionCarEntity([['key' => 'brand', 'type' => 'text', 'required' => true]]),
        [
            'key' => 'Garage',
            'table' => evolutionTable('garages'),
            'fields' => [['key' => 'city', 'type' => 'text', 'required' => true]],
            'relations' => [],
            'routes' => ['admin' => true, 'front' => false, 'api' => false],
        ],
    ]));

    // C'est tout le défaut du n° 111 : la migration était écrite, jamais jouée.
    expect(Schema::hasTable(evolutionTable('garages')))->toBeTrue()
        ->and($result['schema']['created'])->toBe([evolutionTable('garages')])
        // Aucune migration d'évolution : une table neuve se crée par sa propre
        // migration de création, qui n'attendait que d'être exécutée.
        ->and($result['schema']['migrations'])->toBe([]);
});

it('adds the column of a field added after installation', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    expect(Schema::hasColumn(evolutionTable(), 'mileage'))->toBeFalse();

    $result = evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'mileage', 'type' => 'integer'],
    ])]));

    expect(Schema::hasColumn(evolutionTable(), 'mileage'))->toBeTrue()
        ->and($result['schema']['changes'])->toBe(1)
        ->and($result['schema']['migrations'])->toHaveCount(1);
});

it('renames a column when the field declares renamed_from', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    DB::table(evolutionTable())->insert(['brand' => 'Peugeot']);

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'make', 'type' => 'text', 'required' => true, 'renamed_from' => 'brand'],
    ])]));

    expect(Schema::hasColumn(evolutionTable(), 'brand'))->toBeFalse()
        ->and(Schema::hasColumn(evolutionTable(), 'make'))->toBeTrue()
        // Un renommage préserve les données — c'est toute la raison de le
        // déclarer plutôt que de laisser deviner une suppression + un ajout.
        ->and(DB::table(evolutionTable())->value('make'))->toBe('Peugeot');
});

it('refuses to drop a column without an explicit confirmation', function () {
    $draft = evolutionInstalledDraft([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'mileage', 'type' => 'integer'],
    ]);

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
    ])]));
})->throws(DestructiveSchemaChangeNotConfirmedException::class);

it('drops the column once the destruction is confirmed', function () {
    $draft = evolutionInstalledDraft([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'mileage', 'type' => 'integer'],
    ]);

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
    ])]), confirmDestructive: true);

    expect(Schema::hasColumn(evolutionTable(), 'mileage'))->toBeFalse();
});

it('never drops a column the generator does not own', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    // Une colonne posée à la main par le développeur, dans sa propre migration.
    Schema::table(evolutionTable(), function ($table): void {
        $table->string('internal_ref')->nullable();
    });

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
    ])]), confirmDestructive: true);

    // Même destruction confirmée, elle survit : elle n'est dans aucun
    // instantané, donc le générateur ne la revendique pas (spec 01 §5.4).
    expect(Schema::hasColumn(evolutionTable(), 'internal_ref'))->toBeTrue();
});

it('loosens a column that was NOT NULL while its field is optional', function () {
    // Le parc du n° 137 : le module est installé avec la colonne fautive, telle
    // que le générateur l'écrivait avant le correctif.
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    Schema::table(evolutionTable(), function ($table): void {
        $table->text('notes');
    });

    expect(evolutionColumnIsNullable(evolutionTable(), 'notes'))->toBeFalse();

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'notes', 'type' => 'textarea', 'required' => false],
    ])]));

    expect(evolutionColumnIsNullable(evolutionTable(), 'notes'))->toBeTrue();
});

it('refuses to tighten a column that still holds nulls, naming the rows', function () {
    $draft = evolutionInstalledDraft([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'notes', 'type' => 'textarea', 'required' => false],
    ]);

    DB::table(evolutionTable())->insert(['brand' => 'Peugeot', 'notes' => null]);

    evolutionRegenerate($draft, evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'notes', 'type' => 'textarea', 'required' => true],
    ])]));
})->throws(ColumnHasNullsException::class, 'notes');

it('writes nothing at all when the blueprint has not moved', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    $result = evolutionRegenerate($draft, $draft->blueprint);

    expect($result['schema']['migrations'])->toBe([])
        ->and($result['schema']['created'])->toBe([])
        ->and($result['schema']['changes'])->toBe(0)
        ->and(File::glob(evolutionModuleDirectory().'/database/migrations/*_evolve_*.php'))->toBe([]);
});

it('reuses the pending evolution migration instead of stacking a second one', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);

    $target = evolutionBlueprint([evolutionCarEntity([
        ['key' => 'brand', 'type' => 'text', 'required' => true],
        ['key' => 'mileage', 'type' => 'integer'],
    ])]);

    evolutionRegenerate($draft, $target);

    // La migration a été jouée : une seconde régénération identique n'a plus
    // rien à faire, donc n'écrit pas un second ADD COLUMN qui échouerait.
    $again = evolutionRegenerate($draft->fresh() ?? $draft, $target);

    expect($again['schema']['migrations'])->toBe([])
        ->and(File::glob(evolutionModuleDirectory().'/database/migrations/*_evolve_*.php'))->toHaveCount(1);
});

it('refreshes the module permissions of an entity added after installation', function () {
    $draft = evolutionInstalledDraft([['key' => 'brand', 'type' => 'text', 'required' => true]]);
    $module = $draft->module;

    $before = ModulePermission::where('module_id', $module?->id)->pluck('key');

    expect($before)->not->toBeEmpty()
        ->and($before->filter(fn (string $key): bool => str_contains($key, 'garage')))->toBeEmpty();

    evolutionRegenerate($draft, evolutionBlueprint([
        evolutionCarEntity([['key' => 'brand', 'type' => 'text', 'required' => true]]),
        [
            'key' => 'Garage',
            'table' => evolutionTable('garages'),
            'fields' => [['key' => 'city', 'type' => 'text', 'required' => true]],
            'relations' => [],
            'routes' => ['admin' => true, 'front' => false, 'api' => false],
        ],
    ]));

    // Le manifeste est capturé à l'installation et n'était jamais relu (n° 95) :
    // la nouvelle entité générait ses permissions dans module.json sans que
    // personne ne les voie jamais.
    expect(ModulePermission::where('module_id', $module?->id)->pluck('key')->implode(','))
        ->toContain('garage');
});

function evolutionColumnIsNullable(string $table, string $column): bool
{
    foreach (Schema::getColumns($table) as $definition) {
        if ($definition['name'] === $column) {
            return (bool) $definition['nullable'];
        }
    }

    throw new RuntimeException("Colonne {$column} introuvable sur {$table}.");
}
