<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Modules\Models\Module;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

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
 * @param  list<string>  $permissions
 */
function studioRecapActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Recap Actor {$counter}",
        'email' => "studio-recap-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $extraBlueprint
 */
function studioRecapDraft(array $extraBlueprint = []): ModuleBlueprintDraft
{
    static $counter = 0;
    $counter++;

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "garage/fleet-{$counter}",
        'title' => 'Fleet',
        'current_step' => 9,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'garage/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [[
                'key' => 'Car',
                'table' => 'cars',
                'fields' => [['key' => 'plate', 'type' => 'text']],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ]],
            'permissions' => ['auto_crud' => true, 'custom' => []],
            ...$extraBlueprint,
        ],
    ]);
}

it('previews the real file tree and the real file contents, writing nothing', function () {
    $draft = studioRecapDraft();

    $this->actingAs(studioRecapActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 9]))
        ->assertOk()
        ->assertSee('module.json', false)
        ->assertSee('src/Models/Car.php', false)
        ->assertSee('src/Policies/CarPolicy.php', false)
        // Contenu réel du fichier, pas seulement son nom.
        ->assertSee('final class Car extends Model', false)
        ->assertSee('fleet.cars.view', false);

    // L'aperçu n'écrit rien : c'est toute la raison d'être de `plan()`.
    expect(File::isDirectory(generatedModulesPath().'/garage-fleet'))->toBeFalse()
        ->and($draft->fresh()->isGenerated())->toBeFalse();
});

it('generates, installs and activates the module, then marks the draft', function () {
    $draft = studioRecapDraft();

    $this->actingAs(studioRecapActor(), 'baobab')
        ->post(route('admin.studio.generate', $draft))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));

    // Fichiers réellement sur disque.
    expect(File::isFile(generatedModulesPath().'/garage-fleet/module.json'))->toBeTrue()
        ->and(File::isFile(generatedModulesPath().'/garage-fleet/src/Models/Car.php'))->toBeTrue();

    // Module réellement installé et activé.
    $module = Module::where('name', 'garage/fleet')->first();

    expect($module)->not->toBeNull()
        ->and($module->status)->toBe('active');

    // Brouillon marqué, et rattaché au module.
    $draft->refresh();

    expect($draft->isGenerated())->toBeTrue()
        ->and($draft->module_id)->toBe($module->id);
});

it('creates the permissions declared by the wizard when the module is installed', function () {
    $draft = studioRecapDraft([
        'permissions' => ['auto_crud' => true, 'custom' => [['entity' => 'Car', 'key' => 'publish', 'label' => 'Publier']]],
    ]);

    $this->actingAs(studioRecapActor(), 'baobab')
        ->post(route('admin.studio.generate', $draft))
        ->assertRedirect();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(generatedModulesPath().'/garage-fleet/module.json'), associative: true);

    expect(collect((array) $manifest['permissions'])->pluck('key')->all())
        ->toBe(['fleet.cars.view', 'fleet.cars.create', 'fleet.cars.update', 'fleet.cars.delete', 'fleet.cars.publish']);
});

/**
 * La Pass B5 refusait toute seconde génération (409) en renvoyant la
 * régénération à la Pass C. Celle-ci l'a livrée : générer deux fois est
 * désormais légitime, et n'installe ni ne réactive une seconde fois — le
 * détail des conflits est couvert par `StudioRegenerationConflictTest`.
 */
it('allows a second generation, without reinstalling the module', function () {
    $draft = studioRecapDraft();
    $actor = studioRecapActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    $moduleId = $draft->fresh()->module_id;

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft->fresh()))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));

    expect($draft->fresh()->module_id)->toBe($moduleId)
        ->and(Module::where('name', 'garage/fleet')->count())->toBe(1);
});

it('keeps showing the preview once generated, since it can now be regenerated', function () {
    $draft = studioRecapDraft();
    $actor = studioRecapActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft->fresh(), 9]))
        ->assertOk()
        ->assertSee(__('baobab::admin.studio.recap.already_generated'))
        ->assertSee(__('baobab::admin.studio.recap.regenerate_action'))
        // L'aperçu reste : il décrit ce qu'une régénération réécrirait.
        ->assertSee('src/Models/Car.php', false);
});

/**
 * Le seul écart que le brouillon laisse volontairement passer (suivi n° 103) :
 * il doit être rattrapé à l'affichage de l'étape 9, avant le clic, puis refusé
 * à la génération.
 */
it('announces a non-generatable blueprint and refuses to generate it', function () {
    $draft = studioRecapDraft([
        'permissions' => ['auto_crud' => false, 'custom' => []],
    ]);

    $actor = studioRecapActor();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 9]))
        ->assertOk()
        ->assertSee(__('baobab::admin.studio.recap.not_generatable'))
        ->assertDontSee(__('baobab::admin.studio.recap.generate_action'));

    // Même forgée, la soumission ne doit rien écrire.
    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft))
        ->assertRedirect();

    expect(File::isDirectory(generatedModulesPath().'/garage-fleet'))->toBeFalse()
        ->and($draft->fresh()->isGenerated())->toBeFalse();
});

it('refuses to generate an incomplete blueprint without writing anything', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/empty',
        'title' => 'Empty',
        'current_step' => 9,
        // Aucune entité : le schéma strict l'exige.
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => 'garage/empty', 'title' => 'Empty', 'version' => '1.0.0', 'type' => 'module'],
        ],
    ]);

    $this->actingAs(studioRecapActor(), 'baobab')
        ->post(route('admin.studio.generate', $draft))
        ->assertRedirect();

    expect($draft->fresh()->isGenerated())->toBeFalse();
});

it('denies the recap step and the generation without the studio permission', function () {
    $draft = studioRecapDraft();

    $this->actingAs(studioRecapActor([]), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 9]))
        ->assertForbidden();

    $this->actingAs(studioRecapActor([]), 'baobab')
        ->post(route('admin.studio.generate', $draft))
        ->assertForbidden();

    expect($draft->fresh()->isGenerated())->toBeFalse();
});
