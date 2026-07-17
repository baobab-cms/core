<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Themes\Models\ThemeWidgetZone;
use Baobab\Users\Models\User;
use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Core\RichTextWidget;
use Baobab\Widgets\Models\WidgetInstance;

/**
 * @param  list<string>  $permissions
 */
function widgetsHttpActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Widgets HTTP Actor {$counter}",
        'email' => "widgets-http-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the widgets screen without baobab.widgets.manage', function () {
    $actor = widgetsHttpActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.widgets.index'))
        ->assertForbidden();
});

it('renders the widgets board with existing instances grouped by zone', function () {
    ThemeWidgetZone::create(['key' => 'sidebar', 'label' => 'Sidebar', 'is_active' => true]);
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => RichTextWidget::key(), 'settings' => ['content' => 'hi'], 'order' => 0]);
    $actor = widgetsHttpActor(['baobab.widgets.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.widgets.index'))
        ->assertOk()
        ->assertSee('Sidebar');
});

it('renders the settings form for the requested widget type', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.widgets.create', ['widget_key' => RichTextWidget::key(), 'zone_key' => 'sidebar']))
        ->assertOk()
        ->assertSee('name="content"', false);
});

it('creates a widget instance over HTTP', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.widgets.store'), [
            'widget_key' => RichTextWidget::key(),
            'zone_key' => 'sidebar',
            'visibility' => 'everyone',
            'content' => 'Hello',
        ])
        ->assertRedirect(route('admin.widgets.index'));

    expect(WidgetInstance::where('widget_key', RichTextWidget::key())->exists())->toBeTrue();
});

it('refuses to create a custom-html instance without the unsafe_html permission', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.widgets.store'), [
            'widget_key' => CustomHtmlWidget::key(),
            'zone_key' => 'sidebar',
            'visibility' => 'everyone',
            'html' => '<b>x</b>',
        ])
        ->assertForbidden();
});

it('updates a widget instance over HTTP', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);
    $instance = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => RichTextWidget::key(), 'settings' => ['content' => 'old'], 'order' => 0]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.widgets.update', ['instance' => $instance->id]), [
            'zone_key' => 'footer',
            'visibility' => 'everyone',
            'content' => 'new',
        ])
        ->assertRedirect(route('admin.widgets.index'));

    expect($instance->fresh()->zone_key)->toBe('footer')
        ->and($instance->fresh()->settings['content'])->toBe('new');
});

it('deletes a widget instance over HTTP', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);
    $instance = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => RichTextWidget::key(), 'settings' => ['content' => 'x'], 'order' => 0]);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.widgets.destroy', ['instance' => $instance->id]))
        ->assertRedirect(route('admin.widgets.index'));

    expect(WidgetInstance::find($instance->id))->toBeNull();
});

it('moves a widget instance up and down over HTTP', function () {
    $actor = widgetsHttpActor(['baobab.widgets.manage']);
    $first = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => RichTextWidget::key(), 'settings' => [], 'order' => 0]);
    $second = WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => RichTextWidget::key(), 'settings' => [], 'order' => 1]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.widgets.move-up', ['instance' => $second->id]))
        ->assertRedirect(route('admin.widgets.index'));

    expect($second->fresh()->order)->toBe(0)
        ->and($first->fresh()->order)->toBe(1);
});
