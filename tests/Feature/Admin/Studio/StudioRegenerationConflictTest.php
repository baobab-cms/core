<?php

use Baobab\Access\Actions\GrantPermission;
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
function studioRegenActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Regen Actor {$counter}",
        'email' => "studio-regen-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function studioRegenDraft(): ModuleBlueprintDraft
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
                // Même schéma que la fixture partagée : `garage/fleet` est
                // installé par plusieurs tests du même processus, et une table
                // n'a qu'une migration de création (n° 120). Deux schémas sous
                // un même nom de module laissaient le premier installé fixer
                // `cars` pour tous les suivants.
                'fields' => [
                    ['key' => 'brand', 'type' => 'text', 'required' => true],
                    ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]],
                ],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ]],
            'permissions' => ['auto_crud' => true, 'custom' => []],
        ],
    ]);
}

function modelPath(): string
{
    return generatedModulesPath().'/garage-fleet/src/Models/Car.php';
}

it('regenerates an already generated draft without conflict, and reports what it wrote', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    // Aucune modification manuelle : la regeneration passe sans rien demander.
    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft->fresh()))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]))
        ->assertSessionHas('toast');
});

it('sends the user to the diff instead of failing, when a generated file was hand-edited', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    File::put(modelPath(), "<?php\n// écrit à la main\n");

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft->fresh()))
        ->assertRedirect(route('admin.studio.conflicts', $draft));

    // Rien n'a été touché : le conflit est detecte avant la moindre ecriture.
    expect(File::get(modelPath()))->toContain('écrit à la main');
});

it('shows the diff of a hand-edited file, both what is lost and what would be written', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    File::put(modelPath(), "<?php\n// ma ligne bien a moi\n");

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.conflicts', $draft->fresh()))
        ->assertOk()
        ->assertSee('src/Models/Car.php', false)
        // Ce qui disparaitrait.
        ->assertSee('ma ligne bien a moi', false)
        // Ce que la generation ecrirait.
        ->assertSee('final class Car extends Model', false);
});

/**
 * Le comportement que le texte de l'écran promet, et que le premier jet de la
 * Pass C ne tenait pas : on régénère ce qui est sûr, on écrase ce qui a été
 * coché, **et on laisse intact le reste** — pas « tout ou rien ».
 */
it('overwrites what was accepted and leaves the rest untouched, in the same pass', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    $policyPath = generatedModulesPath().'/garage-fleet/src/Policies/CarPolicy.php';

    File::put(modelPath(), "<?php\n// modele modifie\n");
    File::put($policyPath, "<?php\n// policy modifiee\n");

    // Choix fait sur l'ecran de conflit : le modele oui, la policy non.
    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft->fresh()), [
            'resolved' => '1',
            'overwrite' => ['src/Models/Car.php'],
        ])
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));

    expect(File::get(modelPath()))->toContain('final class Car extends Model')
        ->and(File::get($policyPath))->toContain('policy modifiee');
});

it('regenerates everything else even when the user accepts nothing', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    File::put(modelPath(), "<?php\n// modele modifie\n");
    File::delete(generatedModulesPath().'/garage-fleet/src/Policies/CarPolicy.php');

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.generate', $draft->fresh()), ['resolved' => '1'])
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));

    // La modification manuelle survit, le fichier manquant est réécrit.
    expect(File::get(modelPath()))->toContain('modele modifie')
        ->and(File::isFile(generatedModulesPath().'/garage-fleet/src/Policies/CarPolicy.php'))->toBeTrue();
});

it('redirects away from the conflict screen when there is nothing to resolve', function () {
    $draft = studioRegenDraft();
    $actor = studioRegenActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.conflicts', $draft->fresh()))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));
});

it('denies the conflict screen without the studio permission', function () {
    $draft = studioRegenDraft();

    $this->actingAs(studioRegenActor([]), 'baobab')
        ->get(route('admin.studio.conflicts', $draft))
        ->assertForbidden();
});
