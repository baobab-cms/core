<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * @param  list<string>  $permissions
 */
function modulesActor(array $permissions = ['baobab.system.modules.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Modules Actor {$counter}",
        'email' => "modules-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @return array{vendor: string, slug: string}
 */
function blogParams(): array
{
    return ['vendor' => 'acme', 'slug' => 'blog'];
}

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

it('denies every lifecycle route without baobab.system.modules.manage', function () {
    $actor = modulesActor([]);

    $this->actingAs($actor, 'baobab')->get(route('admin.modules.index'))->assertForbidden();
    $this->actingAs($actor, 'baobab')->post(route('admin.modules.install', blogParams()))->assertForbidden();
    $this->actingAs($actor, 'baobab')->post(route('admin.modules.activate', blogParams()))->assertForbidden();
    $this->actingAs($actor, 'baobab')->post(route('admin.modules.deactivate', blogParams()))->assertForbidden();
    $this->actingAs($actor, 'baobab')->delete(route('admin.modules.uninstall', blogParams()))->assertForbidden();

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse();
});

it('lists modules found on disk alongside those already installed', function () {
    $this->actingAs(modulesActor(), 'baobab')
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertSee('acme/blog')
        ->assertSee(__('baobab::admin.modules.status_discovered'))
        ->assertSee(__('baobab::admin.modules.install_action'));
});

it('installs a module over HTTP, running its migrations', function () {
    $this->actingAs(modulesActor(), 'baobab')
        ->post(route('admin.modules.install', blogParams()))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast');

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('installed')
        ->and(Schema::hasTable('acme_blog_posts'))->toBeTrue();
});

it('activates then deactivates a module over HTTP', function () {
    app(InstallModule::class)('acme/blog');
    $actor = modulesActor();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.activate', blogParams()))
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('active');

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.deactivate', blogParams()))
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('inactive');
});

/**
 * La spec §3 distingue les deux : désinstaller retire le module, purger
 * détruit ses données. Sans la case cochée, la table du module survit.
 */
it('uninstalls without touching the module data when purge is not asked', function () {
    app(InstallModule::class)('acme/blog');

    $this->actingAs(modulesActor(), 'baobab')
        ->delete(route('admin.modules.uninstall', blogParams()))
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse()
        ->and(Schema::hasTable('acme_blog_posts'))->toBeTrue();
});

it('rolls back the module migrations when purge is explicitly checked', function () {
    app(InstallModule::class)('acme/blog');

    $this->actingAs(modulesActor(), 'baobab')
        ->delete(route('admin.modules.uninstall', blogParams()), ['purge' => '1'])
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse()
        ->and(Schema::hasTable('acme_blog_posts'))->toBeFalse();
});

/**
 * Défaut relevé en vérification navigateur le 10 août 2026 : la purge ne
 * supprimait rien sur une vraie installation. `migrate:rollback` ne regarde
 * que le **dernier lot**, et les migrations d'un module installé il y a
 * plusieurs lots n'y sont plus — le test ci-dessus ne le voyait pas, puisque
 * l'installation y est toujours la dernière chose migrée.
 *
 * Le test vérifie les deux moitiés : le module part, et la migration d'un
 * autre, jouée après lui, survit.
 */
it('rolls back the module migrations even when they are no longer the last batch', function () {
    app(InstallModule::class)('acme/blog');

    Artisan::call('migrate', [
        '--path' => __DIR__.'/../../Fixtures/migrations-later',
        '--realpath' => true,
        '--force' => true,
    ]);

    expect(Schema::hasTable('unrelated_after_module'))->toBeTrue();

    $this->actingAs(modulesActor(), 'baobab')
        ->delete(route('admin.modules.uninstall', blogParams()), ['purge' => '1'])
        ->assertRedirect(route('admin.modules.index'));

    expect(Schema::hasTable('acme_blog_posts'))->toBeFalse()
        ->and(Schema::hasTable('unrelated_after_module'))->toBeTrue();
});

/**
 * Le message d'échec du cycle de vie est déjà écrit pour un humain : l'écran
 * le montre tel quel plutôt que d'afficher une page 500 ou d'en réécrire un.
 */
it('shows the lifecycle refusal instead of failing, when the module is still active', function () {
    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    $this->actingAs(modulesActor(), 'baobab')
        ->delete(route('admin.modules.uninstall', blogParams()))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error'
            && str_contains((string) $toast['message'], 'désactivez-le d\'abord'));

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('active');
});

it('reports an unknown module rather than throwing', function () {
    $this->actingAs(modulesActor(), 'baobab')
        ->post(route('admin.modules.install', ['vendor' => 'acme', 'slug' => 'ghost']))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');
});

/**
 * Activer un thème n'est pas activer un module (un seul thème actif,
 * emplacements, assets : `ActivateTheme`). L'écran délègue au lieu d'offrir un
 * bouton qui court-circuiterait la règle.
 */
it('offers no activation button for a theme, and points to the themes screen', function () {
    app(InstallModule::class)('acme/theme');

    $this->actingAs(modulesActor(['baobab.system.modules.manage', 'baobab.system.themes.manage']), 'baobab')
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertSee('acme/theme')
        ->assertSee(__('baobab::admin.modules.theme_link'));
});

it('hides the themes link from an actor who cannot manage themes', function () {
    app(InstallModule::class)('acme/theme');

    $this->actingAs(modulesActor(), 'baobab')
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertDontSee(__('baobab::admin.modules.theme_link'));
});

// --- Upload d'archive (Pass B) ----------------------------------------------

function uploadTestPath(): string
{
    return sys_get_temp_dir().'/baobab-test-controller-upload';
}

function moduleArchive(): UploadedFile
{
    $path = uploadTestPath().'/archive.zip';
    File::ensureDirectoryExists(dirname($path));

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', (string) json_encode([
        'name' => 'acme/sent',
        'title' => 'Sent',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Sent\\SentServiceProvider',
    ]));
    $zip->close();

    return new UploadedFile($path, 'sent.zip', 'application/zip', test: true);
}

it('accepts an uploaded archive and lists the module as available, without installing it', function () {
    File::deleteDirectory(uploadTestPath());
    config([
        'baobab.modules.upload.modules_path' => uploadTestPath().'/modules',
        'baobab.modules.paths' => ['local' => [uploadTestPath().'/modules/*']],
    ]);

    $this->actingAs(modulesActor(), 'baobab')
        ->post(route('admin.modules.upload'), ['archive' => moduleArchive()])
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'success');

    expect(File::isFile(uploadTestPath().'/modules/acme-sent/module.json'))->toBeTrue()
        ->and(Module::where('name', 'acme/sent')->exists())->toBeFalse();

    File::deleteDirectory(uploadTestPath());
});

it('shows the archive refusal as a message instead of failing', function () {
    File::deleteDirectory(uploadTestPath());
    File::ensureDirectoryExists(uploadTestPath());
    File::put(uploadTestPath().'/pas-un-zip.zip', 'contenu quelconque');

    $this->actingAs(modulesActor(), 'baobab')
        ->post(route('admin.modules.upload'), [
            'archive' => new UploadedFile(uploadTestPath().'/pas-un-zip.zip', 'pas-un-zip.zip', 'application/zip', test: true),
        ])
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');

    File::deleteDirectory(uploadTestPath());
});

it('denies the upload route without the modules permission', function () {
    $this->actingAs(modulesActor([]), 'baobab')
        ->post(route('admin.modules.upload'))
        ->assertForbidden();
});

it('offers the upload form on the screen, with its warning about running code', function () {
    $this->actingAs(modulesActor(), 'baobab')
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.modules.upload_action'))
        ->assertSee(__('baobab::admin.modules.upload_warning'));
});

/**
 * Installé depuis une **copie** des fixtures : effacer pour de vrai est tout
 * l'intérêt du test, et les fixtures du dépôt ne sont pas jetables.
 */
it('deletes the module files when the screen asks for it', function () {
    $root = uploadTestPath().'/modules';
    File::deleteDirectory(uploadTestPath());
    File::copyDirectory(fixtureModulesPath('local/acme-blog'), $root.'/acme-blog');

    config([
        'baobab.modules.paths' => ['local' => [$root.'/*']],
        'baobab.modules.upload.modules_path' => $root,
    ]);

    app(InstallModule::class)('acme/blog');

    $this->actingAs(modulesActor(), 'baobab')
        ->delete(route('admin.modules.uninstall', blogParams()), ['delete_files' => '1'])
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse()
        ->and(File::exists($root.'/acme-blog'))->toBeFalse()
        // Les fixtures d'origine, elles, sont intactes.
        ->and(File::isFile(fixtureModulesPath('local/acme-blog/module.json')))->toBeTrue();

    File::deleteDirectory(uploadTestPath());
});

/**
 * On assère sur le lien, pas sur le libellé : « Modules » est un mot trop
 * courant dans l'admin pour qu'une absence de texte prouve quoi que ce soit.
 */
it('shows the Modules entry in the sidebar only to an authorised actor', function () {
    $this->actingAs(modulesActor(), 'baobab')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.modules.index'), false);

    $this->actingAs(modulesActor([]), 'baobab')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.modules.index'), false);
});
