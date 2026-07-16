<?php

use Baobab\Actions\Modules\InstallModule;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Themes\Actions\ActivateTheme;
use Baobab\Themes\Actions\GeneratePreviewLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => [
        'local' => [fixtureModulesPath('local/*'), generatedModulesPath().'/*'],
    ]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function buildPreviewCar(): string
{
    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'PreviewCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    return 'preview-cars';
}

it('renders the previewed theme after visiting the signed entry link, without activating it', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');
    $child = Module::where('name', 'acme/theme-child')->firstOrFail();

    $link = app(GeneratePreviewLink::class)($child);

    $this->get($link)->assertRedirect('/');

    expect(session('baobab.preview_theme_id'))->toBe($child->id)
        ->and(Module::where('name', 'acme/theme-child')->value('status'))->toBe('installed');
});

it('rejects an unsigned or tampered preview link', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();

    $this->get("/theme-preview/{$theme->id}")->assertForbidden();
});

it('stops the preview and clears the session flag', function () {
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();
    $link = app(GeneratePreviewLink::class)($theme);
    $this->get($link);

    $this->get('/theme-preview/stop')->assertRedirect('/');

    expect(session('baobab.preview_theme_id'))->toBeNull();
});

it('renders public content with the previewed theme for the previewing session only', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme'); // actif : templates single ET index
    $child = Module::where('name', 'acme/theme-child')->firstOrFail(); // n'a que single
    $prefix = buildPreviewCar();

    // Sans préview : le thème réellement actif (acme/theme) sert la page.
    $this->get("/{$prefix}/peugeot-208")->assertOk()->assertSee('acme-theme single', false);

    // En préview du thème enfant, sur la même session.
    $link = app(GeneratePreviewLink::class)($child);
    $this->get($link);
    $this->get("/{$prefix}/peugeot-208")->assertOk()->assertSee('acme-theme-child single', false);
});
