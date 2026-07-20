<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Baobab\Webhooks\Models\WebhookSubscription;

/**
 * @param  list<string>  $permissions
 */
function webhooksActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Webhooks actor {$counter}",
        'email' => "webhooks-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies access without baobab.system.webhooks.manage', function () {
    $user = webhooksActor([]);

    $this->actingAs($user, 'baobab')->get(route('admin.webhooks.index'))->assertForbidden();
});

it('creates a webhook subscription from the admin form', function () {
    $user = webhooksActor(['baobab.system.webhooks.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.webhooks.store'), [
            'url' => 'https://example.com/hook',
            'secret' => str_repeat('a', 32),
            'events' => ['baobab.content.saved'],
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.webhooks.index'));

    $subscription = WebhookSubscription::where('url', 'https://example.com/hook')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->events)->toBe(['baobab.content.saved'])
        ->and($subscription->is_active)->toBeTrue()
        ->and($subscription->secret)->toBe(str_repeat('a', 32));
});

it('rejects an event not in the catalog', function () {
    $user = webhooksActor(['baobab.system.webhooks.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.webhooks.store'), [
            'url' => 'https://example.com/hook',
            'secret' => str_repeat('a', 32),
            'events' => ['not.a.real.event'],
        ])
        ->assertSessionHasErrors('events.0');
});

it('updates a subscription without changing the secret when the field is left empty', function () {
    $user = webhooksActor(['baobab.system.webhooks.manage']);
    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'original-secret-value-1234567',
        'events' => ['baobab.content.saved'],
        'is_active' => true,
    ]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.webhooks.update', ['subscription' => $subscription->id]), [
            'url' => 'https://example.com/hook-updated',
            'events' => ['baobab.module.activated'],
        ])
        ->assertRedirect(route('admin.webhooks.index'));

    $fresh = $subscription->fresh();

    expect($fresh?->url)->toBe('https://example.com/hook-updated')
        ->and($fresh?->events)->toBe(['baobab.module.activated'])
        ->and($fresh?->secret)->toBe('original-secret-value-1234567');
});

it('resets consecutive_failures when a subscription is manually reactivated', function () {
    $user = webhooksActor(['baobab.system.webhooks.manage']);
    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
        'is_active' => false,
        'consecutive_failures' => 10,
    ]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.webhooks.update', ['subscription' => $subscription->id]), [
            'url' => $subscription->url,
            'events' => $subscription->events,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.webhooks.index'));

    expect($subscription->fresh()?->consecutive_failures)->toBe(0);
});

it('deletes a subscription', function () {
    $user = webhooksActor(['baobab.system.webhooks.manage']);
    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
    ]);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.webhooks.destroy', ['subscription' => $subscription->id]))
        ->assertRedirect(route('admin.webhooks.index'));

    expect(WebhookSubscription::find($subscription->id))->toBeNull();
});
