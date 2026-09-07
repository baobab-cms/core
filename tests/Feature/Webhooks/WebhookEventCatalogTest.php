<?php

use Baobab\Modules\Models\Module;
use Baobab\Webhooks\Support\WebhookEventCatalog;

it('includes the Core catalog from config', function () {
    $events = WebhookEventCatalog::all();

    expect($events)->toContain('baobab.content.saved')
        ->toContain('baobab.module.activated');
});

/**
 * `manual_hooks` (M8 point 6, Pass E, suivi n° 278) : sélectionnable comme
 * n'importe quel autre événement (même catalogue, même validation `in:` de
 * `admin/webhooks`), mais jamais auto-câblé par
 * `registerWebhookDispatchListeners()` — c'est `DispatchWebhookEventTest`
 * qui couvre ce second point, hors de portée de ce catalogue.
 */
it('includes manual_hooks alongside the auto-wired catalog', function () {
    expect(WebhookEventCatalog::all())->toContain('baobab.form.submitted');
});

it('includes events declared by an active module manifest, deduplicated', function () {
    Module::create([
        'name' => 'acme/blog',
        'title' => 'acme/blog',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-blog',
        'manifest' => ['hooks' => ['emits' => ['acme.blog.post_published', 'baobab.content.saved']]],
        'status' => 'active',
    ]);

    $events = WebhookEventCatalog::all();

    expect($events)->toContain('acme.blog.post_published')
        ->and(array_count_values($events)['baobab.content.saved'] ?? 0)->toBe(1);
});

it('ignores an inactive module\'s declared events', function () {
    Module::create([
        'name' => 'acme/inactive',
        'title' => 'acme/inactive',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Inactive\\Providers\\InactiveServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-inactive',
        'manifest' => ['hooks' => ['emits' => ['acme.inactive.never_fires']]],
        'status' => 'inactive',
    ]);

    expect(WebhookEventCatalog::all())->not->toContain('acme.inactive.never_fires');
});
