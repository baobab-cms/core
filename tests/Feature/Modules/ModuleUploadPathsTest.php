<?php

use Baobab\Actions\Modules\UninstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleUploadPaths;
use Illuminate\Support\Facades\File;

/**
 * Reproduit la configuration d'une application qui a **publié** son
 * `config/baobab.php` avant l'ajout du bloc `modules.upload` : `mergeConfigFrom`
 * ne fusionnant que le premier niveau, sa clé `modules` écrase celle du
 * package et le sous-bloc n'existe pas à l'exécution.
 *
 * Cas relevé en vérification navigateur le 10 août 2026 — c'est la
 * configuration réelle du banc d'essai, pas une hypothèse.
 */
function forgetUploadConfig(): void
{
    config(['baobab.modules' => ['paths' => config('baobab.modules.paths')]]);
}

it('falls back to the package defaults when the upload block was shadowed', function () {
    forgetUploadConfig();

    expect(ModuleUploadPaths::modules())->toBe(rtrim(base_path('modules'), '/\\'))
        ->and(ModuleUploadPaths::themes())->toBe(rtrim(base_path('themes'), '/\\'))
        ->and(ModuleUploadPaths::maxSize())->toBe(20 * 1024 * 1024);
});

/**
 * Le symptôme visible du défaut : la règle de validation devenait `max:0`,
 * donc toute archive était refusée avec un message de taille.
 */
it('never yields a size of zero, which would refuse every archive', function () {
    forgetUploadConfig();

    expect(intdiv(ModuleUploadPaths::maxSize(), 1024))->toBeGreaterThan(0);

    config(['baobab.modules.upload.max_size' => 0]);

    expect(ModuleUploadPaths::maxSize())->toBe(20 * 1024 * 1024);
});

it('never yields an empty root, which would target the filesystem root', function () {
    config(['baobab.modules.upload.modules_path' => '', 'baobab.modules.upload.themes_path' => '   ']);

    expect(ModuleUploadPaths::modules())->not->toBe('')
        ->and(ModuleUploadPaths::themes())->not->toBe('')
        ->and(ModuleUploadPaths::roots())->each->not->toBe('');
});

/**
 * Le plus sérieux des trois. Le garde-fou de suppression compare le chemin du
 * module aux racines configurées via `realpath()` — or `realpath('')` retourne
 * le **répertoire de travail courant**. Une racine vide dégradait donc un
 * verrou strict en « tout ce qui vit sous le dossier du projet ».
 */
it('does not let an empty root turn the deletion guard into a blank cheque', function () {
    // Sous le répertoire de travail courant, à dessein : c'est exactement ce
    // que `realpath('')` renvoie, donc le seul endroit où le verrou dégradé
    // laissait réellement passer une suppression.
    $outside = getcwd().'/baobab-test-outside-roots/acme-outside';

    File::ensureDirectoryExists($outside);
    File::put($outside.'/module.json', '{}');

    // Racines volontairement vides, et chemin du module hors de tout.
    config(['baobab.modules.upload.modules_path' => '', 'baobab.modules.upload.themes_path' => '']);

    Module::create([
        'name' => 'acme/outside',
        'title' => 'Outside',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Outside\\OutsideServiceProvider',
        'source' => 'local',
        'path' => $outside,
        'manifest' => ['name' => 'acme/outside'],
        'status' => 'inactive',
    ]);

    app(UninstallModule::class)('acme/outside', purge: false, deleteFiles: true);

    expect(File::isDirectory($outside))->toBeTrue();

    File::deleteDirectory(dirname($outside));
});
