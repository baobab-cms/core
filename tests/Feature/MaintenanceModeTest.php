<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\System\Actions\ToggleMaintenanceMode;
use Baobab\Users\Models\User;

// Le mode maintenance de Laravel est un état fichier (storage/framework/down),
// hors de la base sqlite :memory: que RefreshDatabase réinitialise entre
// chaque test — sans ce filet, un test qui échoue en cours d'activation
// laisserait l'application de test suivante (voire le poste local) en
// maintenance.
afterEach(function (): void {
    app()->maintenanceMode()->deactivate();
});

function maintenanceActor(bool $withToggle): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Maintenance Actor {$counter}",
        'email' => "maintenance-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    if ($withToggle) {
        app(GrantPermission::class)($user, 'baobab.system.maintenance.toggle');
    }

    return $user;
}

it('activates and deactivates maintenance mode', function () {
    expect(app()->maintenanceMode()->active())->toBeFalse();

    app(ToggleMaintenanceMode::class)->activate();

    expect(app()->maintenanceMode()->active())->toBeTrue();

    app(ToggleMaintenanceMode::class)->deactivate();

    expect(app()->maintenanceMode()->active())->toBeFalse();
});

it('returns the resolved bypass secret only on activation, never recomputed', function () {
    $secret = app(ToggleMaintenanceMode::class)->activate(['withSecret' => true]);

    expect($secret)->not->toBeNull()
        ->and(app()->maintenanceMode()->data()['secret'])->toBe($secret);
});

it('serves the rendered maintenance page with a 503 on the public front', function () {
    app(ToggleMaintenanceMode::class)->activate();

    $response = $this->get('/');

    $response->assertStatus(503);
    expect($response->getContent())->toContain(__('baobab::rendering.maintenance_title'));
});

it('blocks an admin without the toggle permission behind the same maintenance page', function () {
    $user = maintenanceActor(withToggle: false);

    app(ToggleMaintenanceMode::class)->activate();

    $response = $this->actingAs($user, 'baobab')->get('/admin');

    $response->assertStatus(503);
    expect($response->getContent())->toContain(__('baobab::rendering.maintenance_title'));
});

it('lets an admin with the toggle permission keep using the admin during maintenance', function () {
    $user = maintenanceActor(withToggle: true);

    app(ToggleMaintenanceMode::class)->activate();

    $response = $this->actingAs($user, 'baobab')->get('/admin');

    $response->assertOk();
});

it('bypasses maintenance via the secret URL', function () {
    $secret = app(ToggleMaintenanceMode::class)->activate(['withSecret' => true]);

    $response = $this->get('/'.$secret);

    $response->assertRedirect();
    $response->assertCookie('laravel_maintenance');
});

it('activates and deactivates maintenance mode via the baobab:down / baobab:up commands', function () {
    $this->artisan('baobab:down', ['--with-secret' => true])->assertExitCode(0);

    expect(app()->maintenanceMode()->active())->toBeTrue();

    $this->artisan('baobab:up')->assertExitCode(0);

    expect(app()->maintenanceMode()->active())->toBeFalse();
});

it('flashes the bypass secret once when activating from the admin screen', function () {
    $user = maintenanceActor(withToggle: true);

    $response = $this->actingAs($user, 'baobab')
        ->from(route('admin.system.maintenance.index'))
        ->post(route('admin.system.maintenance.activate'), ['generate_secret' => true]);

    $response->assertRedirect(route('admin.system.maintenance.index'));
    expect(app()->maintenanceMode()->active())->toBeTrue();

    $index = $this->get(route('admin.system.maintenance.index'));
    $index->assertOk();
    expect($index->getContent())->toContain(__('baobab::admin.maintenance.secret_shown_once'));

    // Un second chargement de l'écran ne doit plus jamais montrer le secret
    // (flash à usage unique, spec 12 §6.1) — ni le proposer à nouveau, ni le
    // recalculer depuis maintenanceMode()->data().
    $reload = $this->get(route('admin.system.maintenance.index'));
    expect($reload->getContent())->not->toContain(__('baobab::admin.maintenance.secret_shown_once'));
});

it('deactivates maintenance from the admin screen', function () {
    $user = maintenanceActor(withToggle: true);
    app(ToggleMaintenanceMode::class)->activate();

    $response = $this->actingAs($user, 'baobab')->post(route('admin.system.maintenance.deactivate'));

    $response->assertRedirect(route('admin.system.maintenance.index'));
    expect(app()->maintenanceMode()->active())->toBeFalse();
});
