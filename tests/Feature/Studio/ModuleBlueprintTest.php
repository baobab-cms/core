<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('accepts a minimal valid blueprint', function () {
    // Le blueprint minimal est déclaré ici plutôt qu'emprunté à la fixture
    // partagée : ce test mesure ce qu'un blueprint *sans rien de facultatif*
    // produit, et il doit rester vrai quand la fixture partagée gagne des
    // champs — ce qu'elle a fait au n° 120, une table n'ayant qu'une seule
    // migration de création et son schéma devant donc être unique par module.
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars']],
    ]));

    expect($blueprint->name())->toBe('garage/fleet')
        ->and($blueprint->title())->toBe('Fleet')
        ->and($blueprint->blueprintVersion())->toBe(1)
        ->and($blueprint->entities())->toBe([['key' => 'Car', 'table' => 'cars']])
        ->and($blueprint->entity('Car'))->toBe(['key' => 'Car', 'table' => 'cars'])
        ->and($blueprint->entity('DoesNotExist'))->toBeNull();
});

it('defaults auto_crud to true and custom permissions to an empty list', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson());

    expect($blueprint->autoCrudEnabled())->toBeTrue()
        ->and($blueprint->customPermissions())->toBe([]);
});

it('accepts explicit permissions', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        // Aucune surface autorisée : couper `auto_crud` tout en laissant
        // l'admin actif (son défaut) est refusé à la génération, cf. le test
        // dédié plus bas.
        'entities' => [['key' => 'Car', 'table' => 'cars', 'routes' => ['admin' => false, 'front' => false, 'api' => false]]],
        'permissions' => [
            'auto_crud' => false,
            'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier']],
        ],
    ]));

    expect($blueprint->autoCrudEnabled())->toBeFalse()
        ->and($blueprint->customPermissions())->toBe([['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier']]);
});

/**
 * Décision du 9 août 2026 (suivi n° 103) : la combinaison est refusée à la
 * génération et **seulement là**. Les contrôleurs admin/API générés autorisent
 * contre la policy, qui mappe toujours les cinq méthodes CRUD : sans les
 * permissions correspondantes, personne ne peut passer.
 */
it('rejects auto_crud disabled while an entity still exposes an authorized surface', function (string $surface) {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'routes' => ['admin' => false, 'front' => false, 'api' => false, $surface => true]]],
        'permissions' => ['auto_crud' => false, 'custom' => []],
    ])))
        ->toThrow(InvalidModuleBlueprintException::class, $surface);
})->with(['admin', 'api']);

it('blocks the dead-on-arrival combination even when routes are left to their defaults', function () {
    // `routes.admin` vaut `true` par défaut : une entité sans bloc `routes`
    // expose bien une surface autorisée.
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'permissions' => ['auto_crud' => false, 'custom' => []],
    ])))
        ->toThrow(InvalidModuleBlueprintException::class, 'admin');
});

it('leaves a front-only entity alone, since its generated controller authorizes nothing', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'routes' => ['admin' => false, 'front' => true, 'api' => false]]],
        'permissions' => ['auto_crud' => false, 'custom' => []],
    ]));

    expect($blueprint->autoCrudEnabled())->toBeFalse();
});

it('never blocks a draft, so the wizard stays explorable between steps 3 and 4', function () {
    $blueprint = ModuleBlueprint::fromDraftJson(moduleBlueprintJson([
        'permissions' => ['auto_crud' => false, 'custom' => []],
    ]));

    expect($blueprint->autoCrudEnabled())->toBeFalse();
});

it('rejects a custom permission attached to an entity that does not exist', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'permissions' => ['custom' => [['entity' => 'Ghost', 'key' => 'publish', 'label' => 'Publier']]],
    ])))
        ->toThrow(InvalidModuleBlueprintException::class, 'Ghost');
});

it('rejects the same custom permission declared twice on one entity', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'permissions' => ['custom' => [
            ['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier'],
            ['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier encore'],
        ]],
    ])))
        ->toThrow(InvalidModuleBlueprintException::class, 'plus d\'une fois');
});

it('rejects malformed JSON', function () {
    expect(fn () => ModuleBlueprint::fromJson('{not json'))
        ->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects a blueprint without identity', function () {
    $json = (string) json_encode(['blueprint_version' => 1, 'entities' => [['key' => 'Car', 'table' => 'cars']]]);

    expect(fn () => ModuleBlueprint::fromJson($json))
        ->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects a blueprint without any entity', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson(['entities' => []])))
        ->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects an identity.type other than module', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'theme'],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects two entities sharing the same key', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars'],
            ['key' => 'Car', 'table' => 'other_cars'],
        ],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects a field of an unknown type', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'brand', 'type' => 'does_not_exist']]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it("rejects a field whose options fail its type's optionsRules", function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'status', 'type' => 'select', 'options' => []]]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('accepts a field of a known type', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'brand', 'type' => 'text']]]],
    ]));

    expect($blueprint->entity('Car')['fields'])->toBe([['key' => 'brand', 'type' => 'text']]);
});

it('accepts a relation targeting a sibling entity in the same blueprint', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars', 'relations' => [
                ['key' => 'driver', 'type' => 'one_to_many', 'target' => 'entity:Driver'],
            ]],
            ['key' => 'Driver', 'table' => 'drivers'],
        ],
    ]));

    expect($blueprint->entity('Car')['relations'])->toBe([
        ['key' => 'driver', 'type' => 'one_to_many', 'target' => 'entity:Driver'],
    ]);
});

it('rejects a relation targeting an entity absent from the blueprint', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'relations' => [
            ['key' => 'driver', 'type' => 'one_to_many', 'target' => 'entity:DoesNotExist'],
        ]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('accepts a relation targeting a known core model', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'relations' => [
            ['key' => 'owner', 'type' => 'one_to_many', 'target' => 'core:User'],
        ]]],
    ]));

    expect($blueprint->entity('Car')['relations'])->toBe([
        ['key' => 'owner', 'type' => 'one_to_many', 'target' => 'core:User'],
    ]);
});

it('accepts a relation targeting an already-built content type', function () {
    app(BuildContentType::class)(contentTypeBlueprintJson('BlueprintRelationTarget'));

    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Fleet', 'table' => 'fleets', 'relations' => [
            ['key' => 'featured', 'type' => 'one_to_many', 'target' => 'content_type:BlueprintRelationTarget'],
        ]]],
    ]));

    expect($blueprint->entity('Fleet')['relations'])->toBe([
        ['key' => 'featured', 'type' => 'one_to_many', 'target' => 'content_type:BlueprintRelationTarget'],
    ]);
});

it('rejects a relation targeting a content type that has not been built yet', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Fleet', 'table' => 'fleets', 'relations' => [
            ['key' => 'featured', 'type' => 'one_to_many', 'target' => 'content_type:DoesNotExist'],
        ]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects a relation of an unknown type', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'relations' => [
            ['key' => 'owner', 'type' => 'has_many', 'target' => 'core:User'],
        ]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

it('rejects an untagged relation target', function () {
    expect(fn () => ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [['key' => 'Car', 'table' => 'cars', 'relations' => [
            ['key' => 'owner', 'type' => 'one_to_many', 'target' => 'User'],
        ]]],
    ])))->toThrow(InvalidModuleBlueprintException::class);
});

describe('fromDraftJson', function () {
    it('accepts a partial draft with no entities at all', function () {
        $blueprint = ModuleBlueprint::fromDraftJson((string) json_encode([
            'identity' => ['name' => 'garage/fleet', 'title' => 'Fleet'],
        ]));

        expect($blueprint->name())->toBe('garage/fleet')
            ->and($blueprint->entities())->toBe([]);
    });

    it('accepts an empty draft', function () {
        $blueprint = ModuleBlueprint::fromDraftJson('{}');

        expect($blueprint->name())->toBeNull()
            ->and($blueprint->entities())->toBe([]);
    });

    it('still cross-validates whatever entities are already present', function () {
        expect(fn () => ModuleBlueprint::fromDraftJson((string) json_encode([
            'entities' => [['key' => 'Car', 'table' => 'cars', 'fields' => [['key' => 'brand', 'type' => 'does_not_exist']]]],
        ])))->toThrow(InvalidModuleBlueprintException::class);
    });

    it('rejects malformed JSON', function () {
        expect(fn () => ModuleBlueprint::fromDraftJson('{not json'))
            ->toThrow(InvalidModuleBlueprintException::class);
    });

    it('rejects a non-object JSON value', function () {
        expect(fn () => ModuleBlueprint::fromDraftJson('"just a string"'))
            ->toThrow(InvalidModuleBlueprintException::class);
    });
});

it('refuse deux entités qui visent la même table', function () {
    // Deux migrations de création pour un seul CREATE TABLE possible : la
    // seconde échouerait en SQL brut à l'installation. Refusé au blueprint,
    // là où la faute se nomme (suivi n° 120).
    ModuleBlueprint::fromJson(moduleBlueprintJson([
        'entities' => [
            ['key' => 'Car', 'table' => 'cars'],
            ['key' => 'Van', 'table' => 'cars'],
        ],
    ]));
})->throws(InvalidModuleBlueprintException::class, 'cars');
