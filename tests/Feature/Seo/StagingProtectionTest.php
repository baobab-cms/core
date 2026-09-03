<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

/**
 * **Le défaut réel, reproduit le 3 septembre 2026 sur une archive
 * décompressée.** `handle()` appelle `$next($request)` en premier : sur `/`,
 * cette étape avait déjà calculé la redirection de `RedirectToInstaller` vers
 * `/install`. C'est la lecture de `seo_settings` **après coup**, non gardée,
 * qui écrasait cette réponse par une 500 — et non l'absence de redirection
 * elle-même. Masqué à la toute première requête d'une archive fraîche : avant
 * que `.env` existe, `APP_ENV` vaut le repli `production` de Laravel, la
 * condition ne s'évaluait donc jamais ; dès la deuxième visite, `.env` existe,
 * `APP_ENV=local` s'applique, et la base absente faisait tomber toute requête
 * publique suivante.
 *
 * **Route choisie à dessein : `/sitemap.xml`, pas une page de contenu.**
 * Une page de contenu (`/staging-pages/...`) ou `/robots.txt` traversent
 * aussi `ComposeSeoMeta`/`ComposeRobotsTxt`, qui lisent `seo_settings` une
 * seconde fois, sans garde — un second défaut, réel mais distinct de
 * celui-ci, consigné à part (suivi n° 243) plutôt que corrigé ici sans
 * cadrage. `RenderSitemapIndex` ne touche pas `seo_settings` et isole donc
 * exactement le point corrigé : le middleware, seul.
 */
it('ne casse pas une réponse déjà calculée quand la base est hors d\'atteinte', function () {
    Schema::drop('seo_settings');

    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex');
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
