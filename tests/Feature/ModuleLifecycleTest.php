<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\DeactivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\UninstallModule;
use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\IncompatibleModuleException;
use Baobab\Modules\Exceptions\ModuleDependencyCycleException;
use Baobab\Modules\Exceptions\ModuleHasActiveDependentsException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Modules\Models\ModulePermission;
use Illuminate\Support\Facades\Schema;

it('installs a module: runs its migrations, persists permissions and menus, fires a hook', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $received = null;
    Hook::listen('baobab.module.installed', function (Module $module) use (&$received) {
        $received = $module;
    });

    $module = app(InstallModule::class)('acme/blog');

    expect($module->status)->toBe('installed')
        ->and($module->name)->toBe('acme/blog')
        ->and(Schema::hasTable('acme_blog_posts'))->toBeTrue()
        ->and($module->permissions()->count())->toBe(2)
        ->and($module->menuItems()->whereNull('parent_id')->count())->toBe(1)
        ->and($module->menuItems()->whereNotNull('parent_id')->count())->toBe(1)
        ->and($received)->not->toBeNull();

    assert($received instanceof Module);
    expect($received->is($module))->toBeTrue();
});

it('activates then deactivates a module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');

    $activated = app(ActivateModule::class)('acme/blog');

    expect($activated->status)->toBe('active')
        ->and($activated->activated_at)->not->toBeNull();

    $deactivated = app(DeactivateModule::class)('acme/blog');

    expect($deactivated->status)->toBe('inactive');
});

it('refuses activation when a required module is not active', function () {
    config(['baobab.modules.paths' => ['composer' => [fixtureModulesPath('composer/*')]]]);

    app(InstallModule::class)('acme/other'); // requires acme/widgets, jamais installé

    app(ActivateModule::class)('acme/other');
})->throws(IncompatibleModuleException::class);

it('refuses deactivation when an active module still depends on it', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]]);

    app(InstallModule::class)('acme/widgets');
    app(InstallModule::class)('acme/other');
    app(ActivateModule::class)('acme/widgets');
    app(ActivateModule::class)('acme/other');

    app(DeactivateModule::class)('acme/widgets');
})->throws(ModuleHasActiveDependentsException::class);

it('refuses installation that would create a dependency cycle', function () {
    config(['baobab.modules.paths' => ['cycle' => [fixtureModulesPath('cycle/*')]]]);

    app(InstallModule::class)('acme/cycle-a');

    app(InstallModule::class)('acme/cycle-b');
})->throws(ModuleDependencyCycleException::class);

it('refuses to uninstall an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    app(UninstallModule::class)('acme/blog');
})->throws(ModuleStillActiveException::class);

it('uninstalls an inactive module and purges its migrations', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    expect(Schema::hasTable('acme_blog_posts'))->toBeTrue();

    app(UninstallModule::class)('acme/blog', true);

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse()
        ->and(Schema::hasTable('acme_blog_posts'))->toBeFalse();
});

it('cascades permission and menu item deletion when a module is uninstalled', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $module = app(InstallModule::class)('acme/blog');
    $moduleId = $module->id;

    app(UninstallModule::class)('acme/blog');

    expect(ModulePermission::where('module_id', $moduleId)->exists())->toBeFalse()
        ->and(ModuleMenuItem::where('module_id', $moduleId)->exists())->toBeFalse();
});
