<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

// ── module:list ───────────────────────────────────────────────────────────────

it('module:list outputs "no modules found" when database is empty', function () {
    config(['baobab.modules.paths' => []]);

    $exitCode = Artisan::call('module:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('No modules found.');
});

it('module:list shows installed modules in a table', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');

    $exitCode = Artisan::call('module:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('acme/blog')
        ->and($output)->toContain('installed');
});

it('module:list shows discovered but uninstalled modules', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $exitCode = Artisan::call('module:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('acme/blog')
        ->and($output)->toContain('discovered');
});

// ── module:install ────────────────────────────────────────────────────────────

it('module:install installs a discovered module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    // Artisan::output() is overwritten by the internal migrate call inside InstallModule,
    // so we verify side-effects and exit code rather than output text.
    $exitCode = Artisan::call('module:install', ['name' => 'acme/blog']);

    expect($exitCode)->toBe(0);
    expect(Module::where('name', 'acme/blog')->exists())->toBeTrue()
        ->and(Schema::hasTable('acme_blog_posts'))->toBeTrue();
});

it('module:install exits with failure for an unknown module', function () {
    config(['baobab.modules.paths' => []]);

    $exitCode = Artisan::call('module:install', ['name' => 'no/such-module']);

    expect($exitCode)->toBe(1);
});

// ── module:activate ───────────────────────────────────────────────────────────

it('module:activate activates an installed module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');

    $exitCode = Artisan::call('module:activate', ['name' => 'acme/blog']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('activated');

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('active');
});

it('module:activate exits with failure for an unknown module', function () {
    $exitCode = Artisan::call('module:activate', ['name' => 'no/such-module']);

    expect($exitCode)->toBe(1);
});

it('module:activate exits with failure when a required dependency is not active', function () {
    config(['baobab.modules.paths' => ['composer' => [fixtureModulesPath('composer/*')]]]);

    app(InstallModule::class)('acme/other');

    $exitCode = Artisan::call('module:activate', ['name' => 'acme/other']);

    expect($exitCode)->toBe(1);
});

// ── module:deactivate ─────────────────────────────────────────────────────────

it('module:deactivate deactivates an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    $exitCode = Artisan::call('module:deactivate', ['name' => 'acme/blog']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('deactivated');

    expect(Module::where('name', 'acme/blog')->value('status'))->toBe('inactive');
});

it('module:deactivate exits with failure when an active module depends on it', function () {
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*')],
        'composer' => [fixtureModulesPath('composer/*')],
    ]]);

    app(InstallModule::class)('acme/widgets');
    app(InstallModule::class)('acme/other');
    app(ActivateModule::class)('acme/widgets');
    app(ActivateModule::class)('acme/other');

    $exitCode = Artisan::call('module:deactivate', ['name' => 'acme/widgets']);

    expect($exitCode)->toBe(1);
});

// ── module:uninstall ──────────────────────────────────────────────────────────

it('module:uninstall removes an inactive module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');

    $exitCode = Artisan::call('module:uninstall', ['name' => 'acme/blog']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('uninstalled');

    expect(Module::where('name', 'acme/blog')->exists())->toBeFalse();
});

it('module:uninstall --purge aborts in non-interactive mode', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');

    // --no-interaction makes confirm() default to false → aborted
    $exitCode = Artisan::call('module:uninstall', [
        'name' => 'acme/blog',
        '--purge' => true,
        '--no-interaction' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Aborted.');

    expect(Module::where('name', 'acme/blog')->exists())->toBeTrue();
});

it('module:uninstall exits with failure when trying to uninstall an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/blog');
    app(ActivateModule::class)('acme/blog');

    $exitCode = Artisan::call('module:uninstall', ['name' => 'acme/blog']);

    expect($exitCode)->toBe(1);
});
