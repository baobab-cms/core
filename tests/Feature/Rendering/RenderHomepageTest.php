<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Themes\Actions\ActivateTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*', fixtureModulesPath('local/*')]]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildHomeCarType(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('HomepagePost', [
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

it('falls back to the Core index template when no reading setting is configured and no theme is active', function () {
    $response = $this->get('/');

    $response->assertOk()->assertViewIs('baobab::templates.index');
});

it('renders the active theme own index template as the homepage when no reading setting is configured', function () {
    app(InstallModule::class)('acme/theme');
    app(ActivateTheme::class)('acme/theme');

    $response = $this->get('/');

    $response->assertOk()->assertSee('acme-theme index');
});

it('renders the chosen static page as the homepage', function () {
    [, $modelClass] = buildHomeCarType();
    $entry = $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    ReadingSetting::create(['mode' => 'static_page', 'page_content_type_key' => 'HomepagePost', 'page_entry_id' => $entry->id]);

    $response = $this->get('/');

    $response->assertOk()->assertSee('Peugeot 208');
});

it('falls back to the default index when the chosen static page is no longer published', function () {
    [, $modelClass] = buildHomeCarType();
    $entry = $modelClass::create(['brand' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

    ReadingSetting::create(['mode' => 'static_page', 'page_content_type_key' => 'HomepagePost', 'page_entry_id' => $entry->id]);

    $response = $this->get('/');

    $response->assertOk()->assertViewIs('baobab::templates.index');
});

it('falls back to the default index when the chosen static page no longer exists', function () {
    [, $modelClass] = buildHomeCarType();

    ReadingSetting::create(['mode' => 'static_page', 'page_content_type_key' => 'HomepagePost', 'page_entry_id' => 999999]);

    $response = $this->get('/');

    $response->assertOk()->assertViewIs('baobab::templates.index');
});

it('renders the latest published entries of the chosen type as the homepage', function () {
    [, $modelClass] = buildHomeCarType();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
    $modelClass::create(['brand' => 'Renault Clio', 'slug' => 'renault-clio', 'status' => 'published']);

    ReadingSetting::create(['mode' => 'latest_posts', 'posts_content_type_key' => 'HomepagePost']);

    $response = $this->get('/');

    $response->assertOk()->assertSee('peugeot-208')->assertSee('renault-clio');
});

it('falls back to the default index when the latest-posts content type no longer exists', function () {
    ReadingSetting::create(['mode' => 'latest_posts', 'posts_content_type_key' => 'GoneType']);

    $response = $this->get('/');

    $response->assertOk()->assertViewIs('baobab::templates.index');
});
