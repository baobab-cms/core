<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\UninstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\PublishThemeAssets;
use Illuminate\Support\Facades\File;

function cleanupRoot(): string
{
    return sys_get_temp_dir().'/baobab-test-uninstall-cleanup';
}

beforeEach(function () {
    File::deleteDirectory(cleanupRoot());
    File::ensureDirectoryExists(cleanupRoot().'/modules');
    File::ensureDirectoryExists(cleanupRoot().'/themes');

    config([
        'baobab.modules.upload.modules_path' => cleanupRoot().'/modules',
        'baobab.modules.upload.themes_path' => cleanupRoot().'/themes',
    ]);
});

afterEach(function () {
    File::deleteDirectory(cleanupRoot());
    removeThemeLink('acme-theme');
});

/**
 * Un module minimal posé sur disque, plus sa ligne `modules`. On n'installe
 * pas via `InstallModule` : ce qui est testé ici est le ménage, pas la pose.
 */
function cleanupModule(string $source = 'local', ?string $path = null, string $type = 'module'): Module
{
    $path ??= cleanupRoot().'/modules/acme-cleanup';

    File::ensureDirectoryExists($path);
    File::put($path.'/module.json', (string) json_encode(['name' => 'acme/cleanup']));

    return Module::create([
        'name' => 'acme/cleanup',
        'title' => 'Cleanup',
        'type' => $type,
        'version' => '1.0.0',
        'provider' => 'Acme\\Cleanup\\CleanupServiceProvider',
        'source' => $source,
        'path' => $path,
        'manifest' => ['name' => 'acme/cleanup', 'type' => $type],
        'status' => 'inactive',
    ]);
}

it('leaves the module files alone when deletion is not asked', function () {
    $module = cleanupModule();

    app(UninstallModule::class)('acme/cleanup');

    expect(File::isDirectory($module->path))->toBeTrue();
});

it('deletes the module files when deletion is explicitly asked', function () {
    $module = cleanupModule();

    app(UninstallModule::class)('acme/cleanup', purge: false, deleteFiles: true);

    expect(File::exists($module->path))->toBeFalse()
        ->and(Module::where('name', 'acme/cleanup')->exists())->toBeFalse();
});

/**
 * `vendor/` appartient à Composer, pas au CMS : supprimer là-dedans casserait
 * l'arbre de dépendances sans que Composer le sache.
 */
it('never deletes the files of a module installed through composer', function () {
    $module = cleanupModule(source: 'composer');

    app(UninstallModule::class)('acme/cleanup', purge: false, deleteFiles: true);

    expect(File::isDirectory($module->path))->toBeTrue()
        // La désinstallation elle-même a bien eu lieu : seul le ménage est refusé.
        ->and(Module::where('name', 'acme/cleanup')->exists())->toBeFalse();
});

/**
 * Le chemin vient de la base. S'il désigne un endroit hors des racines
 * configurées, on ne le suit pas — une ligne `modules` compromise deviendrait
 * sinon une suppression arbitraire sur le serveur.
 */
it('never deletes outside the configured roots, whatever the stored path says', function () {
    $outside = cleanupRoot().'/ailleurs/acme-cleanup';
    $module = cleanupModule(path: $outside);

    app(UninstallModule::class)('acme/cleanup', purge: false, deleteFiles: true);

    expect(File::isDirectory($module->path))->toBeTrue();
});

it('refuses a path that climbs back out of a root with ..', function () {
    $escaping = cleanupRoot().'/modules/../ailleurs';
    File::ensureDirectoryExists(cleanupRoot().'/ailleurs');
    $module = cleanupModule(path: $escaping);

    app(UninstallModule::class)('acme/cleanup', purge: false, deleteFiles: true);

    expect(File::isDirectory(cleanupRoot().'/ailleurs'))->toBeTrue();
});

// --- Assets publiés ---------------------------------------------------------

/**
 * Spec §3 : la désinstallation supprime « les permissions, les entrées de
 * menus, **les assets publiés** ». Les trois premiers existaient depuis M1,
 * le quatrième manquait (suivi n° 115). Sans condition, contrairement aux
 * données et aux fichiers : un lien vers un module parti n'a aucun sens.
 */
it('always removes the published theme assets, without being asked', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $theme = app(InstallModule::class)('acme/theme');
    app(PublishThemeAssets::class)($theme);

    expect(file_exists(public_path('themes/acme-theme')))->toBeTrue();

    app(UninstallModule::class)('acme/theme');

    expect(file_exists(public_path('themes/acme-theme')))->toBeFalse();
});

/**
 * Le lien pointe vers le `public/` du thème : le retirer ne doit jamais
 * traverser jusqu'aux fichiers du module.
 */
it('removes the link without touching the theme files it points to', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $theme = app(InstallModule::class)('acme/theme');
    app(PublishThemeAssets::class)($theme);

    app(UninstallModule::class)('acme/theme');

    expect(File::isDirectory(fixtureModulesPath('local/acme-theme/public')))->toBeTrue()
        ->and(File::isFile(fixtureModulesPath('local/acme-theme/module.json')))->toBeTrue();
});

it('does nothing for a module that is not a theme, and does not fail', function () {
    cleanupModule();

    app(UninstallModule::class)('acme/cleanup');

    expect(Module::where('name', 'acme/cleanup')->exists())->toBeFalse();
});
