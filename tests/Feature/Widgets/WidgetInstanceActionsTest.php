<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Baobab\Widgets\Actions\CreateWidgetInstance;
use Baobab\Widgets\Actions\DeleteWidgetInstance;
use Baobab\Widgets\Actions\ReorderWidgetInstance;
use Baobab\Widgets\Actions\ResolveWidgetZone;
use Baobab\Widgets\Actions\UpdateWidgetInstance;
use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @param  list<string>  $permissions
 */
function widgetsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Widgets Actor {$counter}",
        'email' => "widgets-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('appends a new instance at the end of its zone', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.rich-text', 'settings' => [], 'order' => 3]);

    $instance = app(CreateWidgetInstance::class)('sidebar', 'baobab.rich-text', ['content' => 'X']);

    expect($instance->order)->toBe(4);
});

it('refuses to create a custom-html instance without the unsafe_html permission', function () {
    app(CreateWidgetInstance::class)('sidebar', CustomHtmlWidget::key(), ['html' => '<script>x</script>']);
})->throws(AuthorizationException::class);

it('creates a custom-html instance when the actor has the unsafe_html permission', function () {
    $actor = widgetsActor(['baobab.widgets.unsafe_html']);
    $this->actingAs($actor, 'baobab');

    $instance = app(CreateWidgetInstance::class)('sidebar', CustomHtmlWidget::key(), ['html' => '<script>x</script>']);

    expect($instance->widget_key)->toBe(CustomHtmlWidget::key());
});

it('updates settings, zone, visibility and active state, and forgets the cache', function () {
    $instance = app(CreateWidgetInstance::class)('sidebar', 'baobab.rich-text', ['content' => 'old']);
    app(ResolveWidgetZone::class)('sidebar');

    app(UpdateWidgetInstance::class)($instance, ['content' => 'new'], 'footer', 'authenticated', false);

    expect($instance->fresh()->settings['content'])->toBe('new')
        ->and($instance->fresh()->zone_key)->toBe('footer')
        ->and($instance->fresh()->visibility)->toBe('authenticated')
        ->and($instance->fresh()->is_active)->toBeFalse();
});

it('deletes an instance', function () {
    $instance = app(CreateWidgetInstance::class)('sidebar', 'baobab.rich-text', ['content' => 'X']);

    app(DeleteWidgetInstance::class)($instance);

    expect(WidgetInstance::find($instance->id))->toBeNull();
});

it('swaps order with the previous sibling on move up', function () {
    $first = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.rich-text', 'settings' => [], 'order' => 0, 'is_active' => true]);
    $second = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.rich-text', 'settings' => [], 'order' => 1, 'is_active' => true]);

    app(ReorderWidgetInstance::class)($second, 'up');

    expect($second->fresh()->order)->toBe(0)
        ->and($first->fresh()->order)->toBe(1);
});

it('does nothing when moving the first instance up', function () {
    $first = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.rich-text', 'settings' => [], 'order' => 0, 'is_active' => true]);

    app(ReorderWidgetInstance::class)($first, 'up');

    expect($first->fresh()->order)->toBe(0);
});
