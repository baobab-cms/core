<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Users\Models\User;
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
 * @param  list<string>  $permissions
 */
function seoSettingsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "SEO settings actor {$counter}",
        'email' => "seo-settings-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildSeoSettingsCarType(): array
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'SeoSettingsCar',
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

it('denies access without baobab.system.seo.manage', function () {
    $user = seoSettingsActor([]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.seo.index'))
        ->assertForbidden();
});

it('shows the settings screen for a user with baobab.system.seo.manage', function () {
    $user = seoSettingsActor(['baobab.system.seo.manage']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.seo.index'))
        ->assertOk();
});

it('saves the global settings', function () {
    $user = seoSettingsActor(['baobab.system.seo.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.seo.update'), [
            'site_name' => 'Acme Motors',
            'title_separator' => '·',
            'default_meta_description' => 'Default description',
        ])
        ->assertRedirect(route('admin.seo.index'));

    $setting = SeoSetting::current();

    expect($setting->site_name)->toBe('Acme Motors')
        ->and($setting->title_separator)->toBe('·')
        ->and($setting->default_meta_description)->toBe('Default description');
});

it('saves a title template for a specific content type', function () {
    [$type] = buildSeoSettingsCarType();
    $user = seoSettingsActor(['baobab.system.seo.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.seo.update'), [
            'title_separator' => '—',
            'title_templates' => [
                'SeoSettingsCar' => '{title} — {site_name}',
            ],
        ])
        ->assertRedirect(route('admin.seo.index'));

    expect(SeoContentTypeSetting::forContentType($type)->title_template)->toBe('{title} — {site_name}');
});
