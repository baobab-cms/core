<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Facades\Hook;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\System\Actions\RunHealthChecks;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;

beforeEach(function (): void {
    Storage::fake('local');
});

it('runs and stores the 9 checks', function () {
    $results = app(RunHealthChecks::class)();

    expect($results->storedCheckResults)->toHaveCount(9);
});

it('notifies health viewers when at least one check is failing', function () {
    // Échec forcé par HTTPS/debug (déterministe, sans dépendance à
    // l'outillage local) plutôt que par un vrai défaut d'environnement.
    config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://example.test']);
    Notification::fake();

    $viewer = User::create(['name' => 'Health Viewer', 'email' => 'health-viewer@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($viewer, 'baobab.admin.access');
    app(GrantPermission::class)($viewer, 'baobab.system.health.view');

    app(RunHealthChecks::class)();

    Notification::assertSentTo(
        $viewer,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($viewer)['key'] === 'core.health.failing',
    );
});

it('never notifies for a mere warning, only for a real failing check', function () {
    // Sur une base de test fraîche, SchedulerCheck rapporte "warning"
    // (aucune exécution planifiée enregistrée) — ça ne doit pas notifier.
    config(['app.env' => 'testing']);
    Notification::fake();

    app(RunHealthChecks::class)();

    Notification::assertNothingSent();
});

it('fires baobab.health.checked on every run, success included, for external heartbeat integrations', function () {
    $fired = null;
    Hook::listen('baobab.health.checked', function (StoredCheckResults $results) use (&$fired): void {
        $fired = $results;
    });

    app(RunHealthChecks::class)();

    expect($fired)->toBeInstanceOf(StoredCheckResults::class)
        ->and($fired->storedCheckResults)->toHaveCount(9);
});
