<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Menus\Models\MenuItem;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Themes\Models\ThemeMenuLocation;
use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Core\MenuWidget;
use Baobab\Widgets\Core\RecentContentsWidget;
use Baobab\Widgets\Core\RichTextWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildCoreWidgetCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('CoreWidgetPost', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

it('RecentContentsWidget lists only published entries, most recent first, up to the limit', function () {
    [, $modelClass] = buildCoreWidgetCarType();
    $modelClass::create(['brand' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'published_at' => null]);
    $modelClass::create(['brand' => 'Old', 'slug' => 'old', 'status' => 'published', 'published_at' => now()->subDays(2)]);
    $modelClass::create(['brand' => 'New', 'slug' => 'new', 'status' => 'published', 'published_at' => now()]);

    $instance = new WidgetInstance(['settings' => ['content_type' => 'CoreWidgetPost', 'limit' => 1, 'show_dates' => true]]);

    $data = app(RecentContentsWidget::class)->data($instance);

    expect($data['items'])->toHaveCount(1)
        ->and($data['items'][0]['label'])->toBe('New')
        ->and($data['items'][0]['url'])->toBe('/core-widget-posts/new');
});

it('RecentContentsWidget settingsSchema only lists addressable content types', function () {
    buildCoreWidgetCarType();

    $schema = app(RecentContentsWidget::class)->settingsSchema();
    $contentTypeField = collect($schema)->firstWhere('key', 'content_type');

    expect($contentTypeField['choice_options'])->toHaveKey('CoreWidgetPost');
});

it('MenuWidget delegates to ResolveMenuTree for the configured location', function () {
    ThemeMenuLocation::create(['key' => 'sidebar-menu', 'label' => 'Sidebar', 'is_active' => true]);
    $menu = Menu::create(['name' => 'Sidebar menu']);
    MenuItem::create(['menu_id' => $menu->id, 'order' => 0, 'type' => 'custom_link', 'url' => '/x', 'label' => 'X']);
    MenuAssignment::create(['menu_id' => $menu->id, 'location_key' => 'sidebar-menu']);

    $instance = new WidgetInstance(['settings' => ['location' => 'sidebar-menu']]);

    $data = app(MenuWidget::class)->data($instance);

    expect($data['items'])->toHaveCount(1)
        ->and($data['items'][0]['label'])->toBe('X');
});

it('RichTextWidget sanitizes its HTML output', function () {
    $instance = new WidgetInstance(['settings' => ['content' => '<p>Hello</p><script>alert(1)</script>']]);

    $data = app(RichTextWidget::class)->data($instance);

    expect($data['html'])->toContain('<p>Hello</p>')
        ->and($data['html'])->not->toContain('<script>');
});

it('CustomHtmlWidget returns its HTML output unsanitized', function () {
    $instance = new WidgetInstance(['settings' => ['html' => '<script>alert(1)</script>']]);

    $data = app(CustomHtmlWidget::class)->data($instance);

    expect($data['html'])->toBe('<script>alert(1)</script>');
});
