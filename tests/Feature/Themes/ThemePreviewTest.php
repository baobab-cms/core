<?php

use Acme\ThemeChild\Providers\ThemeChildServiceProvider;
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
    removeThemeLink('acme-theme');
    removeThemeLink('acme-theme-assets');
});

function buildPreviewCar(): string
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('PreviewPage', [
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);

    return 'preview-pages';
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

it('registers the service provider of the previewed theme, which is inactive by definition', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');
    $child = Module::where('name', 'acme/theme-child')->firstOrFail();
    $prefix = buildPreviewCar();

    $this->get(app(GeneratePreviewLink::class)($child));

    // `child-provider-ran` sort de la config fusionnée par `register()`, posée
    // dans la vue par le view composer déclaré en `boot()` : la voir, c'est
    // avoir la preuve que les deux moitiés du cycle ont tourné pour un thème
    // resté `installed` (n° 125).
    $this->get("/{$prefix}/peugeot-208")
        ->assertOk()
        ->assertSee('acme-theme-child single', false)
        ->assertSee('child-provider-ran', false);

    expect(Module::where('name', 'acme/theme-child')->value('status'))->toBe('installed');
});

it('leaves the provider of an inactive theme alone when no preview is running', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme');
    $prefix = buildPreviewCar();

    $this->get("/{$prefix}/peugeot-208")->assertOk();

    expect(app()->getProviders(ThemeChildServiceProvider::class))->toBeEmpty();
});

it('serves the stylesheet of the previewed theme, not the one of the active theme', function () {
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-assets');
    app(ActivateTheme::class)('acme/theme'); // publie /themes/acme-theme (app-test.css)
    $candidate = Module::where('name', 'acme/theme-assets')->firstOrFail();
    $prefix = buildPreviewCar();

    $this->get(app(GeneratePreviewLink::class)($candidate));

    // Deux moitiés du même défaut, prouvées d'un coup (n° 125) : la jonction
    // `public/themes/acme-theme-assets` n'existe que si la préview publie les
    // assets d'un thème jamais activé, et le lien n'est émis que si le
    // composant lit le thème rendu plutôt que le thème actif. Une page qui
    // porterait les gabarits du candidat et le CSS de l'autre est exactement
    // ce qu'un utilisateur voit comme « il manque le CSS ».
    $this->get("/{$prefix}/peugeot-208")
        ->assertOk()
        ->assertSee('acme-theme-assets single', false)
        ->assertSee('/themes/acme-theme-assets/build/assets/app-assets.css', false)
        ->assertDontSee('/themes/acme-theme/build/assets/app-test.css', false);
});

it('renders a previewed theme whose declared provider class does not exist', function () {
    // Un `module.json` peut nommer un provider qu'aucun fichier ne définit —
    // rare pour un thème produit par `baobab:make:theme`, qui en écrit
    // toujours un (vide), mais possible pour un thème écrit à la main, dont
    // l'autoload n'est pas déclaré, ou dont le manifeste a vieilli.
    app(InstallModule::class)('acme/theme');
    app(InstallModule::class)('acme/theme-child');
    app(ActivateTheme::class)('acme/theme-child');
    $parent = Module::where('name', 'acme/theme')->firstOrFail();
    $prefix = buildPreviewCar();

    $this->get(app(GeneratePreviewLink::class)($parent));

    $this->get("/{$prefix}/peugeot-208")->assertOk()->assertSee('acme-theme single', false);
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
