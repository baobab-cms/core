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
function studioPackagingActor(array $permissions = ['baobab.system.studio.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Packaging Actor {$counter}",
        'email' => "studio-packaging-actor-{$counter}@example.com",
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
function studioPackagingDraft(array $extraBlueprint = []): ModuleBlueprintDraft
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

/**
 * @return list<string>
 */
function zipEntries(string $binary): array
{
    $path = tempnam(sys_get_temp_dir(), 'baobab-zip-assert');
    file_put_contents($path, $binary);

    $zip = new ZipArchive;
    $zip->open($path);

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = (string) $zip->getNameIndex($i);
    }

    $zip->close();
    @unlink($path);

    sort($entries);

    return $entries;
}

it('packages a draft that was never generated, writing nothing to /modules', function () {
    $draft = studioPackagingDraft();

    $response = $this->actingAs(studioPackagingActor(), 'baobab')
        ->get(route('admin.studio.download', $draft));

    $response->assertOk()->assertDownload();

    $entries = zipEntries($response->streamedContent());

    expect($entries)->toContain('module.json')
        ->and($entries)->toContain('src/Models/Car.php')
        ->and($entries)->toContain('src/Policies/CarPolicy.php');

    // Toute la raison d'être de `plan()` : empaqueter n'écrit rien sur disque.
    expect(File::isDirectory(generatedModulesPath().'/garage-fleet'))->toBeFalse()
        ->and($draft->fresh()->isGenerated())->toBeFalse();
});

/**
 * `.baobab-checksums.json` est un état de génération local, pas un fichier du
 * module distribué — même exclusion que `baobab:theme:package`.
 */
it('leaves the checksum registry out of the archive', function () {
    $draft = studioPackagingDraft();

    $response = $this->actingAs(studioPackagingActor(), 'baobab')
        ->get(route('admin.studio.download', $draft));

    expect(zipEntries($response->streamedContent()))->not->toContain('.baobab-checksums.json');
});

it('still packages a draft that has already been generated', function () {
    $draft = studioPackagingDraft();
    $actor = studioPackagingActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.generate', $draft))->assertRedirect();

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.download', $draft->fresh()));

    $response->assertOk();

    expect(zipEntries($response->streamedContent()))->toContain('module.json');
});

it('refuses to package a blueprint that could not be generated', function () {
    $draft = studioPackagingDraft(['permissions' => ['auto_crud' => false, 'custom' => []]]);

    $this->actingAs(studioPackagingActor(), 'baobab')
        ->get(route('admin.studio.download', $draft))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 9]));
});

it('offers the download on the recap step, generated or not', function () {
    $draft = studioPackagingDraft();

    $this->actingAs(studioPackagingActor(), 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 9]))
        ->assertOk()
        ->assertSee(route('admin.studio.download', $draft), false);
});

it('denies the download without the studio permission', function () {
    $draft = studioPackagingDraft();

    $this->actingAs(studioPackagingActor([]), 'baobab')
        ->get(route('admin.studio.download', $draft))
        ->assertForbidden();
});
