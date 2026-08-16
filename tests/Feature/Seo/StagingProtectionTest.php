<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('StagingPage', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('forces the noindex meta tag and X-Robots-Tag header outside production', function () {
    $response = $this->get('/staging-pages/peugeot-208');

    $response->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex');
});

it('stops forcing noindex in production', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/staging-pages/peugeot-208');

    $response->assertDontSee('name="robots"', false)
        ->assertHeaderMissing('X-Robots-Tag');
});

it('stops forcing noindex outside production when force_index_on_staging is enabled', function () {
    SeoSetting::current()->fill(['force_index_on_staging' => true])->save();

    $response = $this->get('/staging-pages/peugeot-208');

    $response->assertDontSee('name="robots"', false)
        ->assertHeaderMissing('X-Robots-Tag');
});

it('shows the staging banner in admin when the protection is active', function () {
    $user = User::create(['name' => 'Admin', 'email' => 'staging-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.dashboard'))
        ->assertSee(__('baobab::admin.staging_banner.message'));
});

it('hides the staging banner in admin once force_index_on_staging is enabled', function () {
    SeoSetting::current()->fill(['force_index_on_staging' => true])->save();

    $user = User::create(['name' => 'Admin', 'email' => 'staging-admin-2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.dashboard'))
        ->assertDontSee(__('baobab::admin.staging_banner.message'));
});
