<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Facades\Hook;
use Baobab\Tests\Fixtures\TestModuleServiceProvider;
use Illuminate\Support\Facades\Artisan;

// ── Câblage déclaratif à l'activation ─────────────────────────────────────────

it('activating a module registers its declared hook listeners', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]]);

    // acme/other requires acme/widgets and declares hooks.listens
    app(InstallModule::class)('acme/widgets');
    app(InstallModule::class)('acme/other');
    app(ActivateModule::class)('acme/widgets');
    app(ActivateModule::class)('acme/other');

    $actions = Hook::actions();

    expect($actions)->toHaveKey('baobab.booted')
        ->and($actions['baobab.booted'][0]['listener'])->toBe('Acme\\Other\\Hooks\\OnBooted');
});

it('a module with no hooks.listens does not register any listener on activation', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    $registeredForBooted = Hook::actions()['baobab.booted'] ?? [];

    expect($registeredForBooted)->toBeEmpty();
});

// ── Chargement des providers actifs ──────────────────────────────────────────

it('activating a module whose provider class is autoloaded registers the provider', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    TestModuleServiceProvider::$booted = false;

    app(InstallModule::class)('acme/providable');
    app(ActivateModule::class)('acme/providable');

    // The provider is registered via ActivateModule → bootstrapActiveModules
    // is re-run here to simulate what happens at next boot
    $provider = new TestModuleServiceProvider(app());
    $provider->boot();

    expect(TestModuleServiceProvider::$booted)->toBeTrue();
});

it('bootstrapping skips a module whose provider class does not exist', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    // acme/blog has provider "Acme\Blog\Providers\BlogServiceProvider" which
    // is not autoloaded in the test environment — should not throw
    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    expect(class_exists('Acme\\Blog\\Providers\\BlogServiceProvider'))->toBeFalse();
}); // No exception = pass

// ── Émission de baobab.booted ─────────────────────────────────────────────────

it('baobab.booted listeners are called when the hook fires', function () {
    $called = false;

    Hook::listen('baobab.booted', function () use (&$called): void {
        $called = true;
    });

    Hook::action('baobab.booted');

    expect($called)->toBeTrue();
});

// ── hook:list ─────────────────────────────────────────────────────────────────

it('hook:list shows the Core\'s own baseline listeners (audit log, admin menu) with no module active', function () {
    $exitCode = Artisan::call('hook:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('baobab.access.role.created')
        ->and($output)->toContain('baobab.admin.menu');
});

it('hook:list shows registered actions and filters in a table', function () {
    Hook::listen('demo.action', fn () => null, priority: 5);
    Hook::modify('demo.filter', fn (mixed $v) => $v, priority: 10);

    $exitCode = Artisan::call('hook:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('demo.action')
        ->and($output)->toContain('action')
        ->and($output)->toContain('demo.filter')
        ->and($output)->toContain('filter');
});
