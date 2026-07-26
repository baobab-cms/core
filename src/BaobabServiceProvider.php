<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Access\AccessManager;
use Baobab\Access\Facades\Access;
use Baobab\Access\Http\Middleware\ImpersonationGuard;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Baobab\Admin\Content\Http\Controllers\ValidationQueueController;
use Baobab\Admin\Sidebar\SidebarBuilder;
use Baobab\Admin\Sidebar\SidebarItem;
use Baobab\Api\GraphQL\Actions\CompileGraphqlSchema;
use Baobab\Api\Http\Middleware\EnsureApiEnabled;
use Baobab\Api\Http\Middleware\EnsureGraphqlEnabled;
use Baobab\Api\Http\Middleware\HandleApiCors;
use Baobab\Api\Models\ApiSetting;
use Baobab\Api\Support\ProblemDetailsRenderer;
use Baobab\Audit\AuditLogger;
use Baobab\Auth\Models\PersonalAccessToken;
use Baobab\Auth\TwoFactorManager;
use Baobab\Branding\Actions\CompileDesignTokens;
use Baobab\Branding\Actions\SyncThemeFonts;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Console\Commands\ContentPublishDueCommand;
use Baobab\Console\Commands\ContentPurgeTrashCommand;
use Baobab\Console\Commands\ContentTypeBuildCommand;
use Baobab\Console\Commands\ContentTypeMakeCommand;
use Baobab\Console\Commands\ContentUnpublishDueCommand;
use Baobab\Console\Commands\DesignTokensCompileCommand;
use Baobab\Console\Commands\FontsListCommand;
use Baobab\Console\Commands\GraphqlCompileCommand;
use Baobab\Console\Commands\HookListCommand;
use Baobab\Console\Commands\MailTemplatesCommand;
use Baobab\Console\Commands\MailTestCommand;
use Baobab\Console\Commands\MediaPurgeTrashCommand;
use Baobab\Console\Commands\MediaRegenerateCommand;
use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Console\Commands\NotFoundPurgeCommand;
use Baobab\Console\Commands\NotificationsPurgeCommand;
use Baobab\Console\Commands\NotifyTestCommand;
use Baobab\Console\Commands\OpenApiCompileCommand;
use Baobab\Console\Commands\SearchReindexCommand;
use Baobab\Console\Commands\SearchStatusCommand;
use Baobab\Console\Commands\SeoSitemapCommand;
use Baobab\Console\Commands\SuperAdminCommand;
use Baobab\Console\Commands\ThemeActivateCommand;
use Baobab\Console\Commands\ThemeMakeCommand;
use Baobab\Console\Commands\ThemePreviewCommand;
use Baobab\Console\Commands\ThemeValidateCommand;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Fields\Types\BooleanField;
use Baobab\ContentTypes\Fields\Types\DateField;
use Baobab\ContentTypes\Fields\Types\DateTimeField;
use Baobab\ContentTypes\Fields\Types\DecimalField;
use Baobab\ContentTypes\Fields\Types\FileField;
use Baobab\ContentTypes\Fields\Types\GalleryField;
use Baobab\ContentTypes\Fields\Types\ImageField;
use Baobab\ContentTypes\Fields\Types\IntegerField;
use Baobab\ContentTypes\Fields\Types\JsonField;
use Baobab\ContentTypes\Fields\Types\MultiSelectField;
use Baobab\ContentTypes\Fields\Types\RadioField;
use Baobab\ContentTypes\Fields\Types\RichTextField;
use Baobab\ContentTypes\Fields\Types\SelectField;
use Baobab\ContentTypes\Fields\Types\SlugField;
use Baobab\ContentTypes\Fields\Types\TextareaField;
use Baobab\ContentTypes\Fields\Types\TextField;
use Baobab\ContentTypes\Fields\Types\TimeField;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Hooks\HookRegistry;
use Baobab\Media\Actions\SyncMediaUsagesFromEntry;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Menus\Actions\InvalidateMenuCacheForEntry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Notify\Notifier;
use Baobab\Rendering\PublicRouteRegistrar;
use Baobab\Scheduler\SchedulerRegistrar;
use Baobab\Search\SearchRegistry;
use Baobab\Search\Sources\ContentsSearchSource;
use Baobab\Search\Sources\MediaSearchSource;
use Baobab\Search\Sources\UsersSearchSource;
use Baobab\Seo\Actions\CreateRedirect;
use Baobab\Seo\Actions\DeleteRedirect;
use Baobab\Seo\Actions\InvalidateSitemapCache;
use Baobab\Seo\Actions\UpdateRedirect;
use Baobab\Seo\Actions\UpdateSeoMeta;
use Baobab\Seo\Models\Redirect;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Seo\SeoContext;
use Baobab\Support\Logger as SupportLogger;
use Baobab\Users\Models\User;
use Baobab\Webhooks\Actions\DispatchWebhookEvent;
use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Core\MenuWidget;
use Baobab\Widgets\Core\RecentContentsWidget;
use Baobab\Widgets\Core\RichTextWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\WidgetRegistry;
use BladeUI\Icons\BladeIconsServiceProvider;
use Davidhsianturi\BladeBootstrapIcons\BladeBootstrapIconsServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Mews\Purifier\PurifierServiceProvider;
use Nuwave\Lighthouse\Async\AsyncServiceProvider;
use Nuwave\Lighthouse\Auth\AuthServiceProvider as LighthouseAuthServiceProvider;
use Nuwave\Lighthouse\Bind\BindServiceProvider;
use Nuwave\Lighthouse\Cache\CacheServiceProvider as LighthouseCacheServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider;
use Nuwave\Lighthouse\Http\Middleware\AcceptJson;
use Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Nuwave\Lighthouse\Testing\TestingServiceProvider as LighthouseTestingServiceProvider;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider as LighthouseValidationServiceProvider;
use OwenVoke\BladeFontAwesome\BladeFontAwesomeServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Sitemap\SitemapServiceProvider;
use Throwable;

class BaobabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baobab.php', 'baobab');

        $this->registerSpatieConfig();

        $this->app->register(PermissionServiceProvider::class);
        $this->app->register(PurifierServiceProvider::class);
        $this->app->register(SitemapServiceProvider::class);
        $this->app->register(SanctumServiceProvider::class);
        $this->registerLighthouseProviders();
        $this->configureGraphqlRoute();
        $this->app->register(ScoutServiceProvider::class);
        $this->app->register(BladeIconsServiceProvider::class);
        $this->app->register(BladeBootstrapIconsServiceProvider::class);
        $this->app->register(BladeFontAwesomeServiceProvider::class);

        $this->configurePurifier();

        $this->app->singleton(HookRegistry::class);

        $this->app->singleton(AccessManager::class);

        $this->app->singleton(SidebarBuilder::class);

        $this->app->singleton(AuditLogger::class);

        $this->app->singleton(PermissionMatrixBuilder::class);

        $this->app->singleton(TwoFactorManager::class, fn () => new TwoFactorManager(new Google2FA));

        $this->app->singleton(SupportLogger::class);

        $this->app->singleton(FieldRegistry::class);

        $this->app->singleton(WidgetRegistry::class);

        $this->app->singleton(PresetRegistry::class);

        $this->app->singleton(SearchRegistry::class);

        $this->app->singleton(SeoContext::class);

        $this->app->singleton(ImageManager::class, function (Application $app): ImageManager {
            /** @var string $driver */
            $driver = $app->make('config')->get('baobab.media.image_driver', 'gd');

            return $driver === 'imagick' ? ImageManager::imagick() : ImageManager::gd();
        });

        $this->app->bind(ModuleDiscovery::class, function (Application $app) {
            /** @var array<string, list<string>> $paths */
            $paths = $app->make('config')->get('baobab.modules.paths', []);

            return new ModuleDiscovery($paths);
        });

        $this->registerGuard();

        $this->registerLogging();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
        $this->loadAdminRoutes();
        $this->registerSanctumGuard();
        $this->excludeApiFromDefaultCors();
        $this->registerApiRateLimiter();
        $this->loadApiRoutes();
        $this->registerApiExceptionRendering();
        $this->registerApiDocsRoutes();
        $this->configureScout();
        $this->registerThemePreviewRoutes();
        $this->registerPublicRoutes();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'baobab');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'baobab');
        // Composants à classe (logique de rendu hors des vues, ex. <x-baobab::img>) —
        // les composants anonymes de resources/views/components restent résolus en repli.
        Blade::componentNamespace('Baobab\\View\\Components', 'baobab');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/baobab.php' => config_path('baobab.php'),
        ], 'baobab-config');

        // Super Admin bypasses all Gate checks.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super-admin', 'baobab') ? true : null;
        });

        // Alias facade.
        $this->app->alias(AccessManager::class, Access::class);

        $this->registerAdminSidebarComposer();

        $this->registerImpersonationBannerComposer();

        $this->registerStagingNoindexBannerComposer();

        $this->registerBrandingComposer();

        $this->registerNotificationCenterComposer();

        $this->registerAuditListeners();

        $this->registerMediaUsageListener();

        $this->registerMenuCacheInvalidationListener();

        $this->registerWidgetCacheInvalidationListener();

        $this->registerSeoFormSection();

        $this->registerSeoSaveListener();

        $this->registerSlugRedirectListener();

        $this->registerSitemapCacheInvalidationListener();

        $this->registerGraphqlSchemaCompilationListener();
        $this->registerDesignTokenCompilationListener();

        $this->registerWorkflowNotificationListeners();

        $this->registerSecurityNotificationListeners();

        $this->registerWebhookDispatchListeners();

        $this->registerCoreSidebarItems();

        $this->registerCoreFieldTypes();

        $this->registerCoreWidgets();

        $this->registerCorePresets();

        $this->registerCoreSearchSources();

        $this->bootstrapActiveModules();

        Hook::action('baobab.booted');

        if ($this->app->runningInConsole()) {
            $this->fixWindowsConsoleCharset();

            $this->commands([
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleActivateCommand::class,
                ModuleDeactivateCommand::class,
                ModuleUninstallCommand::class,
                HookListCommand::class,
                SuperAdminCommand::class,
                ContentTypeBuildCommand::class,
                ContentTypeMakeCommand::class,
                MediaRegenerateCommand::class,
                MediaPurgeTrashCommand::class,
                ContentPublishDueCommand::class,
                ContentUnpublishDueCommand::class,
                ContentPurgeTrashCommand::class,
                MailTestCommand::class,
                MailTemplatesCommand::class,
                NotifyTestCommand::class,
                NotificationsPurgeCommand::class,
                ThemeActivateCommand::class,
                ThemePreviewCommand::class,
                ThemeValidateCommand::class,
                ThemeMakeCommand::class,
                NotFoundPurgeCommand::class,
                SeoSitemapCommand::class,
                GraphqlCompileCommand::class,
                DesignTokensCompileCommand::class,
                FontsListCommand::class,
                OpenApiCompileCommand::class,
                SearchReindexCommand::class,
                SearchStatusCommand::class,
            ]);
        }

        $this->registerMediaPurgeSchedule();

        $this->registerNotFoundPurgeSchedule();

        $this->registerSchedulerTasks();
    }

    /**
     * Tâches Core (publication/dépublication programmées, spec 09 §4) et
     * tâches déclarées par les modules actifs (`schedule` au manifeste,
     * spec 12 §2), toutes enregistrées et journalisées par
     * SchedulerRegistrar — moteur générique, M5 point 7.
     */
    private function registerSchedulerTasks(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $this->app->make(SchedulerRegistrar::class)->register($schedule);
        });
    }

    /**
     * `media:purge-trash` (M4 point 3) embarquée directement par le package
     * plutôt que documentée pour ajout manuel au Kernel de l'app
     * consommatrice — patron standard Laravel pour qu'un package fournisse
     * sa propre tâche planifiée.
     */
    private function registerMediaPurgeSchedule(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->command(MediaPurgeTrashCommand::class)->daily();
        });
    }

    /**
     * `seo:purge-404-log` (spec 07 §4) — même patron que
     * `registerMediaPurgeSchedule()`.
     */
    private function registerNotFoundPurgeSchedule(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->command(NotFoundPurgeCommand::class)->daily();
        });
    }

    /**
     * PHP CLI sur Windows lit/écrit la console dans le codepage OEM actif
     * (souvent CP437/850), pas en UTF-8 : un accent tapé dans un prompt
     * interactif (`content-type:make`) est mal décodé puis persisté tel
     * quel en base — pas un problème de charset SQLite/MySQL, la
     * corruption a lieu avant l'écriture. Sans effet sur Linux/macOS (guard
     * `PHP_OS_FAMILY`) ni sur les requêtes HTTP (toujours UTF-8). CMS
     * francophone-first : les accents doivent survivre à la CLI.
     */
    private function fixWindowsConsoleCharset(): void
    {
        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_cp_set')) {
            sapi_windows_cp_set(65001);
        }
    }

    /**
     * Liste blanche HTML nettoyée à la sauvegarde d'un champ `richtext`
     * (spec 02 §3.2, `RichTextField::cast()` → `CleanHtml`). La config par
     * défaut de mews/purifier n'autorisait ni les titres, ni les citations,
     * ni le texte barré — pourtant déjà proposés par la toolbar Tiptap
     * (M4 point 4a) : ils étaient silencieusement supprimés à
     * l'enregistrement. Étendue pour couvrir exactement ce que la toolbar
     * produit (gras/italique/souligné/barré/code, surlignage `<mark>`,
     * titres H2-H4, citation, listes, lien, séparateur, alignement via
     * `style` sur les blocs) — jamais au-delà, la liste blanche reste le
     * garde-fou contre l'injection.
     */
    private function configurePurifier(): void
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        $config->set(
            'purifier.settings.default.HTML.Allowed',
            'div,p[style],br,h2[style],h3[style],h4[style],blockquote,b,strong,i,em,u,s,mark,code,ul,ol,li,a[href|title],img[width|height|alt|src],hr,span[style]',
        );
    }

    private function loadAdminRoutes(): void
    {
        Route::middleware(['web', 'auth:baobab', 'verified', 'can:baobab.admin.access', ImpersonationGuard::class])
            ->prefix($this->app->make('config')->get('baobab.admin.path', 'admin'))
            ->name('admin.')
            ->group(__DIR__.'/../routes/admin.php');
    }

    /**
     * Laravel enregistre par défaut `Illuminate\Http\Middleware\HandleCors`
     * en middleware global (`config('cors.paths')` inclut `api/*` même sans
     * `config/cors.php` publié — Laravel 11 fusionne le stub du framework,
     * cf. `vendor/laravel/framework/config/cors.php`). Middleware global =
     * plus extérieur que la pile de route `loadApiRoutes()`, donc il
     * s'exécute APRÈS `HandleApiCors` en phase de réponse et écrase
     * `Access-Control-Allow-Origin` avec son propre défaut (`*`) — bug
     * découvert en écrivant les tests Pass B (l'en-tête attendu par
     * origine devenait toujours `*`). `/api/*` est entièrement réservé à
     * Baobab dans cette architecture (aucun autre usage attendu côté app
     * hôte) : on retire ce chemin de `cors.paths` pour laisser
     * `HandleApiCors` seul juge, sans toucher `sanctum/csrf-cookie`
     * (toujours nécessaire au flux SPA Sanctum).
     */
    private function excludeApiFromDefaultCors(): void
    {
        $paths = array_values(array_diff(
            (array) $this->app->make('config')->get('cors.paths', []),
            ['api/*'],
        ));

        $this->app->make('config')->set('cors.paths', $paths);
    }

    /**
     * REST v1 (spec 08 §2, M7 points 1-2) — préfixe déjà réservé côté
     * blueprint (`baobab.rendering.reserved_prefixes`,
     * `ContentTypeBlueprint::validateUrlPrefix()`), structurellement
     * disjoint de la route générique publique `/{prefix}/{slug?}` (celle-ci
     * ne matche jamais plus de 2 segments).
     *
     * `EnsureFrontendRequestsAreStateful` (Sanctum), pas le groupe `web` en
     * dur (patron M7 point 1, corrigé ici) : ce middleware applique la
     * pile session/cookies/CSRF complète **seulement** quand
     * `Origin`/`Referer` correspond à un domaine « statefull »
     * (`config('sanctum.stateful')`, couvre déjà `APP_URL` automatiquement,
     * `Sanctum::currentApplicationUrlWithPort()`) — une session admin
     * déjà connectée (navigateur, même origine) continue de fonctionner
     * exactement comme en Pass A/B, mais un vrai client Bearer externe
     * (aucune origine correspondante) n'a plus à satisfaire une exigence
     * CSRF qui n'a jamais de sens pour lui (le CSRF protège l'auth par
     * cookie, pas l'auth par token). Le guard `sanctum`
     * (`registerSanctumGuard()`) fait le reste : session ou Bearer, une
     * seule résolution d'acteur côté contrôleur REST.
     *
     * Trois middlewares transverses ajoutés en M7 point 2 Pass B (spec 08
     * §4.3), dans cet ordre précis : `HandleApiCors` répond au préflight
     * `OPTIONS` avant même l'interrupteur global (une requête de
     * préflight ne doit jamais être bloquée par une vérification qui ne la
     * concerne pas) ; `EnsureApiEnabled` (interrupteur global, le moins
     * cher à vérifier) ; `EnsureFrontendRequestsAreStateful` ; enfin
     * `throttle:baobab-api` — nommé ainsi, pas `api`, pour ne jamais
     * collisionner avec un limiteur que l'app hôte définirait elle-même
     * (même rationale que la table Sanctum dédiée, Pass A).
     */
    private function loadApiRoutes(): void
    {
        Route::middleware([
            HandleApiCors::class,
            EnsureApiEnabled::class,
            EnsureFrontendRequestsAreStateful::class,
            'throttle:baobab-api',
        ])
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Lighthouse (M7 point 3) est distribué comme un ensemble de
     * sous-providers optionnels — chacun n'enregistre que ce dont sa
     * fonctionnalité a besoin (ex. `PaginationServiceProvider` seul connaît
     * la directive `@paginate`, `Nuwave\Lighthouse\Pagination`, jamais
     * chargée par `LighthouseServiceProvider::boot()` qui n'annonce que
     * `Schema\Directives` au hook `RegisterDirectiveNamespaces`). En usage
     * normal, la découverte de paquets Laravel les enregistre tous depuis
     * `composer.json` (`extra.laravel.providers`, la liste exacte reproduite
     * ci-dessous) ; Testbench ne fait pas cette découverte (patron déjà
     * établi pour Sanctum/Sitemap/Permission, cf. `register()`), donc chacun
     * doit être explicite ici pour que le package fonctionne identiquement
     * en test et en application hôte réelle.
     */
    private function registerLighthouseProviders(): void
    {
        $this->app->register(LighthouseServiceProvider::class);
        $this->app->register(AsyncServiceProvider::class);
        $this->app->register(LighthouseAuthServiceProvider::class);
        $this->app->register(BindServiceProvider::class);
        $this->app->register(LighthouseCacheServiceProvider::class);
        $this->app->register(GlobalIdServiceProvider::class);
        $this->app->register(OrderByServiceProvider::class);
        $this->app->register(PaginationServiceProvider::class);
        $this->app->register(SoftDeletesServiceProvider::class);
        $this->app->register(LighthouseTestingServiceProvider::class);
        $this->app->register(LighthouseValidationServiceProvider::class);
    }

    /**
     * Lighthouse enregistre sa propre route `/graphql` depuis son `boot()`
     * (config `lighthouse.route.*`, jamais un fichier de routes à charger
     * nous-mêmes) — configurer plutôt que remplacer. **Doit s'exécuter
     * pendant `register()`, pas `boot()`** : appelée depuis `boot()`
     * jusqu'à cette découverte (Pass B, M7 point 3), cette méthode
     * s'exécutait bien *après* `LighthouseServiceProvider::boot()`, pas
     * avant comme le docblock précédent l'affirmait — `Application::register()`
     * n'ajoute un provider à la liste bootée qu'*après* le retour de son
     * propre `register()` ; comme `LighthouseServiceProvider` est enregistré
     * *depuis l'intérieur* de `BaobabServiceProvider::register()`
     * (`registerLighthouseProviders()`), il est ajouté à cette liste avant
     * `BaobabServiceProvider` lui-même, donc bouté avant lui. Conséquence
     * réelle, restée invisible faute de test exerçant `/graphql` avec un
     * réglage non défaut : la route ne portait jamais que les deux
     * middlewares par défaut de Lighthouse (`AcceptJson`,
     * `AttemptAuthentication`) — jamais `HandleApiCors`/l'interrupteur
     * dédié/`throttle:baobab-api`, un `Route::middleware` étant figé
     * définitivement à l'appel de `addRoute()`, contrairement à un guard
     * Auth (résolu paresseusement à chaque requête, cf. `registerSanctumGuard()`,
     * qui n'a donc pas ce problème en restant dans `boot()`). Le déplacement
     * en `register()` suffit : le repository de config est déjà résolvable
     * à ce stade, et toutes les phases `register()` de tous les providers
     * s'exécutent avant la moindre phase `boot()`. Middlewares transverses
     * identiques au REST (`loadApiRoutes()`) + les deux propres à Lighthouse
     * (`AcceptJson`, `AttemptAuthentication` — celle-ci n'est pas strictement
     * nécessaire, les résolveurs GraphQL résolvent l'acteur eux-mêmes via
     * `ApiActor`, comme REST, mais la garder évite une divergence si une
     * directive `@guard` apparaît un jour). `lighthouse.guards` fixé à
     * `sanctum` seul plutôt que le défaut Laravel (potentiellement `web`,
     * jamais utilisé par cette app). `EnsureGraphqlEnabled` remplace
     * `EnsureApiEnabled` (M7 point 3 Pass B, spec 08 §3.3/§4.3) :
     * interrupteur dédié, indépendant de `rest_enabled`, qui configure aussi
     * l'introspection pour cette requête (lue dynamiquement à chaque requête,
     * donc non affectée par ce bug). Profondeur/complexité posées ici en dur
     * (« config Lighthouse native », pas un réglage admin) — un GraphQL sans
     * limites est un déni de service en libre-service ; également non
     * affectées, lues à la construction du validateur, pas à l'enregistrement
     * de la route.
     */
    private function configureGraphqlRoute(): void
    {
        $config = $this->app->make('config');

        $config->set('lighthouse.guards', ['sanctum']);
        $config->set('lighthouse.schema_path', storage_path('app/baobab/graphql/schema.graphql'));
        $config->set('lighthouse.security.max_query_depth', 10);
        $config->set('lighthouse.security.max_query_complexity', 1000);
        $config->set('lighthouse.route.middleware', [
            HandleApiCors::class,
            EnsureGraphqlEnabled::class,
            EnsureFrontendRequestsAreStateful::class,
            'throttle:baobab-api',
            AcceptJson::class,
            AttemptAuthentication::class,
        ]);
    }

    /**
     * Configuration Scout (spec 11 §2, M7 point 5 Pass A) — driver `database`
     * par défaut (zéro dépendance externe), indexation en queue sur
     * `baobab-low` (patron webhooks/mail). Contrairement à
     * `configureGraphqlRoute()`, aucune contrainte d'ordre entre `register()`
     * et `boot()` : `EngineManager`/`Searchable::syncWithSearchUsing()` lisent
     * `config('scout.*')` paresseusement, au moment d'une recherche/synchronisation
     * réelle, jamais au moment de la résolution du conteneur — un `config()`
     * posé n'importe où avant la première requête suffit. `scout.queue` doit
     * être un tableau (`connection`/`queue`), pas le booléen `true` du stub
     * vendor par défaut : `Searchable::syncWithSearchUsing()` lit
     * `scout.queue.connection` par dot-notation, qui ne résout à rien sur un
     * simple booléen (repli silencieux sur `queue.default`).
     */
    private function configureScout(): void
    {
        config([
            'scout.driver' => 'database',
            'scout.queue' => ['connection' => 'baobab-low', 'queue' => 'baobab-low'],
        ]);
    }

    /**
     * Limiteur nommé `baobab-api` (spec 08 §4.3) — keyé par utilisateur
     * (session guard `baobab` *ou* Bearer token, tous deux résolus par le
     * guard `sanctum`) quand authentifié, repli sur l'IP sinon : satisfait
     * à la fois « par token » et « par IP pour les endpoints publics » avec
     * une seule règle. Pas d'override par-token pour cette version (décidé
     * en Pass A) — un seul réglage global (`ApiSetting::rate_limit_per_minute`),
     * lu à l'exécution comme toute requête normale (jamais au `boot()`
     * lui-même — seule l'*enregistrement* du limiteur a lieu ici, son
     * corps ne s'exécute qu'à chaque requête réelle). Aucun
     * `RouteServiceProvider` dans cette app Laravel 11 minimale — patron
     * déjà établi de tout garder côté package plutôt que de dépendre d'un
     * fichier de l'app hôte.
     */
    private function registerApiRateLimiter(): void
    {
        RateLimiter::for('baobab-api', function (Request $request): Limit {
            $key = Auth::guard('sanctum')->id() ?? $request->ip();

            return Limit::perMinute(ApiSetting::current()->rate_limit_per_minute)->by((string) $key);
        });
    }

    /**
     * Rendu RFC 9457 des erreurs `/api/*` (spec 08 §2.2) — enregistré ici,
     * depuis le package, plutôt que dans le `bootstrap/app.php` de
     * l'application hôte : `$exceptions->render()` (mécanisme habituel
     * documenté par Laravel) n'existe qu'au bootstrap de l'app réelle,
     * jamais sous Orchestra Testbench (les tests du package n'exécutent que
     * `BaobabServiceProvider`, jamais `bootstrap/app.php`) — et une
     * installation de `baobab/core` comme dépendance Composer ne doit pas
     * exiger de modification du `bootstrap/app.php` de l'app hôte pour que
     * son contrat d'erreur fonctionne. `Handler::renderable()` est le même
     * mécanisme sous-jacent, appelable depuis n'importe où après résolution
     * du handler dans le conteneur.
     */
    /**
     * `graphql` ajoutée au périmètre (M7 point 3 Pass B) : une exception HTTP
     * levée *avant* l'exécution GraphQL (`EnsureGraphqlEnabled` → 404,
     * `throttle:baobab-api` → 429 — les mêmes classes d'exception que le
     * REST, jamais une erreur de requête GraphQL elle-même) doit recevoir le
     * même contrat RFC 9457 que le REST plutôt que le rendu JSON par défaut
     * de Laravel. Les erreurs de requête GraphQL propres (validation,
     * exécution, `Unauthenticated.` résolu par les résolveurs eux-mêmes)
     * restent inchangées : Lighthouse les convertit en `{"errors": [...]}`
     * avec un 200, jamais une exception HTTP — ce renderer ne les voit
     * jamais.
     */
    private function registerApiExceptionRendering(): void
    {
        /** @var Handler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(fn (Throwable $e, Request $request) => $request->is('api/*') || $request->is('graphql')
            ? $this->app->make(ProblemDetailsRenderer::class)->render($e)
            : null);
    }

    /**
     * Entrée/sortie de préview de thème (spec 03 §7, M6 point 2) — deux
     * routes signées/simples, patron closures de `routes/web.php`. Doit être
     * chargé avant `registerPublicRoutes()` : `/theme-preview/*` doit être
     * tenté avant la route générique `/{prefix}/{slug?}`, qui matcherait
     * sinon en premier (même forme d'URI).
     */
    private function registerThemePreviewRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/theme-preview.php');
    }

    /**
     * `/api/docs` (spec 08 §7, M7 point 4b Pass B) — groupe `web` requis :
     * `EnsureApiDocsEnabled` résout l'acteur via `Auth::guard('baobab')`,
     * qui s'appuie sur la session.
     */
    private function registerApiDocsRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/api-docs.php');
    }

    /**
     * Routes publiques des types adressables (spec 03 §3, M6 point 1) — route
     * générique résolue à la requête (PublicRouteRegistrar), pas de lecture
     * DB au boot : aucune garde Schema::hasTable nécessaire ici.
     */
    private function registerPublicRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $this->app->make(PublicRouteRegistrar::class)->register($router);
    }

    private function registerAdminSidebarComposer(): void
    {
        View::composer('baobab::layouts.partials.admin-sidebar', function (ViewContract $view): void {
            /** @var SidebarBuilder $builder */
            $builder = $this->app->make(SidebarBuilder::class);

            /** @var User|null $user */
            $user = auth('baobab')->user();

            $view->with('sidebar', $builder->build($user));
        });
    }

    private function registerImpersonationBannerComposer(): void
    {
        View::composer('baobab::layouts.partials.impersonation-banner', function (ViewContract $view): void {
            /** @var User|null $impersonatedUser */
            $impersonatedUser = session('baobab.impersonator_id') ? auth('baobab')->user() : null;

            $view->with('impersonatedUser', $impersonatedUser);
        });
    }

    /**
     * Bandeau admin visible quand la protection des environnements
     * (spec 07 §8, Pass C) est active — même condition que la fusion
     * noindex de `ComposeSeoMeta` et l'en-tête de `ForceStagingNoindexHeader`.
     */
    private function registerStagingNoindexBannerComposer(): void
    {
        View::composer('baobab::layouts.partials.staging-noindex-banner', function (ViewContract $view): void {
            $active = ! $this->app->environment('production') && ! SeoSetting::current()->force_index_on_staging;

            $view->with('stagingNoindexActive', $active);
        });
    }

    /**
     * Réglages de marque (spec-admin.md §11.1) partagés avec le layout admin —
     * calculés ici, jamais dans la vue (`@include` hérite du scope du parent,
     * donc la topbar y a accès sans second composer).
     */
    private function registerBrandingComposer(): void
    {
        View::composer('baobab::layouts.admin', function (ViewContract $view): void {
            $view->with('branding', BrandingSetting::current()->load(['logo', 'favicon']));
        });
    }

    /**
     * Centre de notifications (spec 11 §8) — seul le nombre de non-lus est
     * calculé côté serveur (peinture initiale de la cloche) ; la liste elle-
     * même est chargée en JS via l'endpoint `admin.notifications.poll`, pas
     * dupliquée ici.
     */
    private function registerNotificationCenterComposer(): void
    {
        View::composer('baobab::layouts.partials.admin-topbar', function (ViewContract $view): void {
            /** @var User|null $user */
            $user = auth('baobab')->user();

            $view->with('unreadNotificationsCount', $user?->unreadNotifications()->count() ?? 0);
        });
    }

    /**
     * Le workflow de validation est le premier client des notifications
     * (spec 09 §5, spec 11 §6, M5 point 4) : soumission → détenteurs de
     * `publish_any` sur le type, approbation/rejet → auteur. Écoute les
     * hooks déjà émis par les Actions de M5 point 3, ne les modifie pas.
     * Une même paire de closures couvre les variantes natives et
     * working-draft-review : leurs hooks partagent la même forme
     * ($contentType, $entry[, $comment]).
     */
    private function registerWorkflowNotificationListeners(): void
    {
        /** @var HookRegistry $registry */
        $registry = $this->app->make(HookRegistry::class);

        $notifyReviewers = function (ContentType $contentType, Model $entry): void {
            $reviewers = $this->usersWithPermission('content.'.Str::snake($contentType->key).'.publish_any');

            if ($reviewers->isEmpty()) {
                return;
            }

            $this->app->make(Notifier::class)->send(
                'core.content.review_requested',
                $reviewers,
                $this->contentNotificationData($contentType, $entry),
            );
        };

        $notifyAuthorApproved = function (ContentType $contentType, Model $entry): void {
            $this->notifyContentAuthor($contentType, $entry, 'core.content.review_approved');
        };

        $notifyAuthorRejected = function (ContentType $contentType, Model $entry, string $comment): void {
            $this->notifyContentAuthor($contentType, $entry, 'core.content.review_rejected', ['comment' => $comment]);
        };

        $registry->listen('baobab.content.submitted', $notifyReviewers);
        $registry->listen('baobab.content.working_draft.submitted', $notifyReviewers);
        $registry->listen('baobab.content.approved', $notifyAuthorApproved);
        $registry->listen('baobab.content.working_draft.approved', $notifyAuthorApproved);
        $registry->listen('baobab.content.rejected', $notifyAuthorRejected);
        $registry->listen('baobab.content.working_draft.rejected', $notifyAuthorRejected);
    }

    /**
     * Notification de sécurité `core.security.*` (spec 11 §6, `configurable:
     * false`) — seule « impersonation subie » est câblée ici (suivi n° 47) :
     * « nouvel appareil » et « changement de mot de passe » n'ont pas
     * d'infra/flow dédié dans le code actuel, câblage laissé différé.
     */
    private function registerSecurityNotificationListeners(): void
    {
        /** @var HookRegistry $registry */
        $registry = $this->app->make(HookRegistry::class);

        $registry->listen('baobab.user.impersonation.started', function (User $actor, User $target): void {
            $this->app->make(Notifier::class)->send(
                'core.security.impersonation_started',
                [$target],
                ['actor_name' => $actor->name, 'occurred_at' => now()->toIso8601String()],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function notifyContentAuthor(ContentType $contentType, Model $entry, string $notificationKey, array $extra = []): void
    {
        /** @var int|null $authorId */
        $authorId = $entry->getAttribute('author_id');
        $author = $authorId !== null ? User::find($authorId) : null;

        if ($author === null) {
            return;
        }

        $this->app->make(Notifier::class)->send(
            $notificationKey,
            [$author],
            $this->contentNotificationData($contentType, $entry, $extra),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithPermission(string $permission): Collection
    {
        // Permission::findByName() (utilisée par le scope ->permission()) lève
        // si la permission n'a encore jamais été accordée à personne — état
        // normal pour un type de contenu qui vient d'activer le workflow.
        if (! Permission::where('name', $permission)->where('guard_name', 'baobab')->exists()) {
            return new Collection;
        }

        return User::permission($permission)->get();
    }

    /**
     * Webhooks sortants (spec 08 §5, M7 point 4 Pass A) : câble le catalogue
     * Core (`config('baobab.webhooks.hooks')`) sur `DispatchWebhookEvent`,
     * indépendamment de l'état de la base — cette Action porte elle-même la
     * garde `Schema::hasTable()`, pas ce câblage. Les événements déclarés par
     * les modules actifs (`manifest['hooks']['emits']`) sont câblés depuis
     * `bootstrapActiveModules()`, qui a déjà la liste sous la main.
     */
    private function registerWebhookDispatchListeners(): void
    {
        /** @var HookRegistry $registry */
        $registry = $this->app->make(HookRegistry::class);

        /** @var list<string> $coreHooks */
        $coreHooks = $this->app->make('config')->get('baobab.webhooks.hooks', []);

        foreach ($coreHooks as $hook) {
            $registry->listen($hook, fn (mixed ...$args) => $this->app->make(DispatchWebhookEvent::class)($hook, array_values($args)));
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function contentNotificationData(ContentType $contentType, Model $entry, array $extra = []): array
    {
        $titleField = $contentType->blueprint['title_field'] ?? ($contentType->blueprint['fields'][0]['key'] ?? null);
        $title = $titleField !== null ? (string) $entry->getAttribute($titleField) : '#'.$entry->getKey();
        $slug = Str::kebab(Str::plural($contentType->key));

        return array_merge([
            'content_type' => $contentType->blueprint['label']['singular'] ?? $contentType->key,
            'content_title' => $title,
            'url' => route('admin.content.edit', ['contentType' => $slug, 'entry' => $entry->getKey()]),
        ], $extra);
    }

    /**
     * Journal d'audit (spec 04 §7, spec 05 §6.4) : écoute les hooks déjà émis
     * par AccessManager plutôt que de modifier ses Actions — le service reste
     * un consommateur du système de hooks comme n'importe quel autre listener.
     */
    private function registerAuditListeners(): void
    {
        /** @var HookRegistry $registry */
        $registry = $this->app->make(HookRegistry::class);

        $audit = fn (): AuditLogger => $this->app->make(AuditLogger::class);

        $registry->listen('baobab.access.role.created', function (Role $role) use ($audit): void {
            $audit()->record('role.created', $role, ['name' => $role->name, 'level' => $role->getAttribute('level')]);
        });

        $registry->listen('baobab.access.role.updated', function (Role $role, array $before) use ($audit): void {
            $audit()->record('role.updated', $role, [
                'before' => ['name' => $before['name'] ?? null, 'level' => $before['level'] ?? null],
                'after' => ['name' => $role->name, 'level' => $role->getAttribute('level')],
            ]);
        });

        $registry->listen('baobab.access.role.deleted', function (Role $role) use ($audit): void {
            $audit()->record('role.deleted', null, ['name' => $role->name, 'level' => $role->getAttribute('level')]);
        });

        $registry->listen('baobab.access.granted', function (Model $to, string $permission) use ($audit): void {
            $audit()->record('permission.granted', $to, ['permission' => $permission]);
        });

        $registry->listen('baobab.access.revoked', function (Model $from, string $permission) use ($audit): void {
            $audit()->record('permission.revoked', $from, ['permission' => $permission]);
        });

        $registry->listen('baobab.access.role.assigned', function (User $user, Role $role) use ($audit): void {
            $audit()->record('role.assigned', $user, ['role' => $role->name]);
        });

        $registry->listen('baobab.access.role.removed', function (User $user, Role $role) use ($audit): void {
            $audit()->record('role.removed', $user, ['role' => $role->name]);
        });

        $registry->listen('baobab.user.impersonation.started', function (User $actor, User $target) use ($audit): void {
            $audit()->record('user.impersonation.started', $target, ['actor' => $actor->name]);
        });

        $registry->listen('baobab.user.impersonation.ended', function (User $actor, ?User $target, string $reason) use ($audit): void {
            $audit()->record('user.impersonation.ended', $target, ['reason' => $reason]);
        });
    }

    /**
     * Resynchronise les usages de médias (M4 point 3, spec 06 §5) à chaque
     * sauvegarde de contenu — écoute le hook déclenché par SaveContentEntry.
     * `$data` (M4 point 4b-ii) porte la sélection des champs `gallery`, qui
     * n'ont aucune colonne propre pour la relire depuis `$entry`.
     */
    private function registerMediaUsageListener(): void
    {
        Hook::listen('baobab.content.saved', function (ContentType $contentType, Model $entry, bool $isNew, array $data = []): void {
            app(SyncMediaUsagesFromEntry::class)($contentType, $entry, $data);
        });
    }

    /**
     * Invalide le cache d'un menu dès qu'une entrée qu'il référence est
     * sauvegardée ou change de statut éditorial (spec 10 §2.4) — l'URL/le
     * libellé résolus ou la visibilité (masqué si dépublié/en corbeille)
     * peuvent avoir changé.
     */
    private function registerMenuCacheInvalidationListener(): void
    {
        $invalidate = function (Model $entry): void {
            app(InvalidateMenuCacheForEntry::class)($entry);
        };

        Hook::listen('baobab.content.saved', function (ContentType $contentType, Model $entry, bool $isNew, array $data = []) use ($invalidate): void {
            $invalidate($entry);
        });

        Hook::listen('baobab.content.transitioned', function (ContentType $contentType, Model $entry, string $from, string $to, ?User $actor = null) use ($invalidate): void {
            $invalidate($entry);
        });
    }

    /**
     * Contrairement aux menus (FK polymorphe explicite sur l'item, cible
     * précisément invalidable), les réglages d'un widget sont un JSON opaque
     * — impossible de savoir statiquement de quel contenu dépend une
     * instance sans exécuter sa requête. Simplification documentée (suivi
     * des différés) : invalide toutes les instances du widget « Contenus
     * récents » à chaque sauvegarde/transition de contenu, pas seulement
     * celles concernées — nombre d'instances toujours modeste en pratique.
     */
    private function registerWidgetCacheInvalidationListener(): void
    {
        $invalidate = function (): void {
            WidgetInstance::where('widget_key', RecentContentsWidget::key())
                ->get()
                ->each(fn (WidgetInstance $instance) => Cache::forget("baobab.widget.{$instance->id}"));
        };

        Hook::listen('baobab.content.saved', function (ContentType $contentType, Model $entry, bool $isNew, array $data = []) use ($invalidate): void {
            $invalidate();
        });

        Hook::listen('baobab.content.transitioned', function (ContentType $contentType, Model $entry, string $from, string $to, ?User $actor = null) use ($invalidate): void {
            $invalidate();
        });
    }

    /**
     * Injecte la metabox SEO (spec 07 §2.1) après la liste de champs de
     * chaque formulaire de contenu adressable, via `baobab.content.form.sections`
     * — pas `baobab.content.form.fields` (câblé uniquement pour des champs
     * plats validés par `validationRules()`, valeur lue par `getAttribute()` :
     * inadapté à une metabox qui vit dans sa propre table polymorphique,
     * cf. docblock de `ContentController::formSections()`). `$entry` absent
     * en création : la metabox s'affiche quand même, vide, pour permettre de
     * pré-remplir le SEO dès la création (même POST que le contenu).
     */
    private function registerSeoFormSection(): void
    {
        Hook::modify('baobab.content.form.sections', function (array $sections, ContentType $type, ?Model $entry) {
            if (! $type->is_addressable) {
                return $sections;
            }

            $seoMeta = $entry !== null ? SeoMeta::forEntry($entry) : new SeoMeta;

            $previewUrl = null;

            if ($entry !== null && $entry->getAttribute('slug') !== null) {
                $previewUrl = url("/{$type->urlPrefix()}/{$entry->getAttribute('slug')}");
            }

            $sections[] = view('baobab::admin.seo.metabox', [
                'seoMeta' => $seoMeta,
                'previewUrl' => $previewUrl,
                'previewFallbackTitle' => $this->label($type),
            ])->render();

            return $sections;
        });
    }

    private function label(ContentType $type): string
    {
        return $type->blueprint['label']['singular'] ?? $type->key;
    }

    /**
     * Sauvegarde la metabox SEO (spec 07 §2.1) — lit `request()->input('seo', [])`
     * directement plutôt que `$data` (le tableau validé transmis par
     * `baobab.content.saved` ne porte que les champs déclarés dans
     * `ContentController::validationRules()`, jamais filtré par
     * `baobab.content.form.fields`/`.sections` : découplage total,
     * `UpdateSeoMeta` valide et journalise lui-même).
     */
    private function registerSeoSaveListener(): void
    {
        Hook::listen('baobab.content.saved', function (ContentType $contentType, Model $entry, bool $isNew, array $data = []): void {
            if (! $contentType->is_addressable) {
                return;
            }

            app(UpdateSeoMeta::class)($entry, (array) request()->input('seo', []));
        });
    }

    /**
     * Historique de slug → redirection 301 automatique (spec 07 §3) : toute
     * modification du slug d'une entrée déjà publiée d'un type adressable
     * crée (ou met à jour, si l'ancienne URL avait déjà une redirection —
     * cas d'un renommage A→B puis B→A) une redirection de l'ancienne URL
     * vers la nouvelle. Écouté sur `baobab.content.saving` (avant
     * `$entry->fill($data)` dans `SaveContentEntry`), pas `.saved` :
     * `save()` appelle `syncOriginal()` en interne, donc `getOriginal()`
     * reflète déjà la **nouvelle** valeur une fois `.saved` émis —
     * `wasChanged()` reste vrai mais l'ancienne valeur n'est plus
     * récupérable à ce stade. Comparer `$entry` (encore intact) à `$data`
     * directement, avant la sauvegarde, est le seul point fiable.
     */
    private function registerSlugRedirectListener(): void
    {
        Hook::listen('baobab.content.saving', function (ContentType $contentType, Model $entry, array $data = []): void {
            if (! $entry->exists || ! $contentType->is_addressable || ! array_key_exists('slug', $data)) {
                return;
            }

            $oldSlug = $entry->getAttribute('slug');
            $newSlug = $data['slug'];

            if ($oldSlug === null || $newSlug === null || $oldSlug === $newSlug) {
                return;
            }

            $source = "/{$contentType->urlPrefix()}/{$oldSlug}";
            $target = "/{$contentType->urlPrefix()}/{$newSlug}";

            // Le nouveau slug redevient l'URL vivante : une redirection
            // existante partant de cette même URL est désormais périmée et
            // formerait une boucle (ex. A→B puis un renommage retour B→A
            // sans ce nettoyage laisserait A→B ET B→A actives en même
            // temps). Supprimée plutôt que mise à jour — sa cible n'a plus
            // de sens une fois la source redevenue vivante.
            $stale = Redirect::where('source', $target)->where('source_kind', 'auto')->first();

            if ($stale !== null) {
                app(DeleteRedirect::class)($stale);
            }

            $existing = Redirect::where('source', $source)->first();

            if ($existing !== null) {
                app(UpdateRedirect::class)($existing, ['target' => $target, 'status_code' => 301, 'is_active' => true, 'source_kind' => 'auto']);

                return;
            }

            app(CreateRedirect::class)(['source' => $source, 'target' => $target, 'status_code' => 301, 'source_kind' => 'auto']);
        });
    }

    /**
     * Le sitemap d'un Content Type dépend de ses propres entrées
     * uniquement (spec 07 §5 : « cache invalidé à la publication/
     * modification ») — l'index n'est jamais concerné ici, la liste des
     * types éligibles ne change pas quand une entrée est sauvegardée
     * (seule une bascule `exclude_from_sitemap`, gérée par
     * `UpdateSeoSettings`, peut la faire varier).
     */
    private function registerSitemapCacheInvalidationListener(): void
    {
        $invalidate = function (ContentType $contentType): void {
            if ($contentType->is_addressable) {
                app(InvalidateSitemapCache::class)->forType($contentType->key);
            }
        };

        Hook::listen('baobab.content.saved', function (ContentType $contentType, Model $entry, bool $isNew, array $data = []) use ($invalidate): void {
            $invalidate($contentType);
        });

        Hook::listen('baobab.content.transitioned', function (ContentType $contentType, Model $entry, string $from, string $to, ?User $actor = null) use ($invalidate): void {
            $invalidate($contentType);
        });
    }

    /**
     * Schéma GraphQL global (M7 point 3, spec 08 §3.2) recompilé à chaque
     * événement qui change la liste des types exposés ou leurs champs —
     * jamais résolu à la volée (patron `CompileDesignTokens`, spec 18 §4.2).
     * `content_type.created`/`.evolved` couvrent le fragment lui-même ;
     * `module.activated`/`.deactivated` couvrent sa présence dans
     * l'assemblage (un Content Type désactivé disparaît du schéma sans
     * modification de son fragment).
     */
    private function registerGraphqlSchemaCompilationListener(): void
    {
        $compile = function (): void {
            app(CompileGraphqlSchema::class)();
        };

        Hook::listen('baobab.content_type.created', fn (ContentType $contentType) => $compile());
        Hook::listen('baobab.content_type.evolved', fn (ContentType $contentType, array $diff = []) => $compile());
        Hook::listen('baobab.module.activated', fn (Module $module) => $compile());
        Hook::listen('baobab.module.deactivated', fn (Module $module) => $compile());
    }

    /**
     * Recompile l'artefact de design tokens (spec 18 §4.2) à chaque
     * déclencheur : activation d'un thème (`theme.json` change de niveau 2
     * de la cascade) et sauvegarde du branding (niveau 4). Redondant avec
     * l'appel direct déjà fait par `UpdateBrandingSettings` pour ce dernier
     * cas — le hook reste le point d'extension pour tout futur écrivain de
     * `branding_settings.tokens` (profils de marque, Pass B) sans dépendre
     * de cette Action précise.
     *
     * `SyncThemeFonts` s'exécute avant la compilation à l'activation d'un
     * thème (spec 18 §5.3, Pass B) — jamais en injection directe dans
     * `ActivateTheme` (Themes), pour ne pas coupler ce module à Branding :
     * même choix que ce hook lui-même plutôt qu'une dépendance directe.
     */
    private function registerDesignTokenCompilationListener(): void
    {
        $compile = function (): void {
            app(CompileDesignTokens::class)();
        };

        Hook::listen('baobab.theme.activated', function (?Module $previous, Module $activated) use ($compile): void {
            app(SyncThemeFonts::class)($activated);
            $compile();
        });
        Hook::listen('baobab.branding.tokens.saved', fn (BrandingSetting $setting) => $compile());
    }

    /**
     * Le Core est son propre premier consommateur du hook d'extension de la
     * sidebar (spec 04 §3.3) : les écrans Audit/Accès ne viennent pas d'un
     * module, donc pas de module_menu_items — on les injecte comme le ferait
     * n'importe quel listener externe.
     */
    private function registerCoreSidebarItems(): void
    {
        Hook::modify('baobab.admin.menu', function (Collection $items, ?User $user) {
            if ($user === null) {
                return $items;
            }

            $coreItems = [];

            if ($user->can('baobab.access.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -1,
                    label: __('baobab::admin.sidebar.access'),
                    icon: 'bi-shield-lock',
                    url: route('admin.access.index'),
                    order: -20,
                );
            }

            if ($user->can('baobab.audit.view')) {
                $coreItems[] = new SidebarItem(
                    id: -2,
                    label: __('baobab::admin.sidebar.audit'),
                    icon: 'bi-journal-text',
                    url: route('admin.audit.index'),
                    order: -10,
                );
            }

            if ($user->can('baobab.users.impersonate')) {
                $coreItems[] = new SidebarItem(
                    id: -3,
                    label: __('baobab::admin.sidebar.users'),
                    icon: 'bi-people',
                    url: route('admin.users.index'),
                    order: -30,
                );
            }

            if ($user->can('baobab.media.view')) {
                $coreItems[] = new SidebarItem(
                    id: -4,
                    label: __('baobab::admin.sidebar.media'),
                    icon: 'bi-images',
                    url: route('admin.media.index'),
                    order: -40,
                );
            }

            if (ValidationQueueController::isVisibleTo($user)) {
                $coreItems[] = new SidebarItem(
                    id: -5,
                    label: __('baobab::admin.sidebar.review'),
                    icon: 'bi-check2-square',
                    url: route('admin.review.index'),
                    order: -25,
                );
            }

            if ($user->can('baobab.system.branding.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -6,
                    label: __('baobab::admin.sidebar.branding'),
                    icon: 'bi-palette',
                    url: route('admin.branding.index'),
                    order: -15,
                );
            }

            if ($user->can('baobab.system.themes.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -7,
                    label: __('baobab::admin.sidebar.themes'),
                    icon: 'bi-brush',
                    url: route('admin.themes.index'),
                    order: -16,
                );
            }

            if ($user->can('baobab.menus.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -8,
                    label: __('baobab::admin.sidebar.menus'),
                    icon: 'bi-list-nested',
                    url: route('admin.menus.index'),
                    order: -17,
                );
            }

            if ($user->can('baobab.widgets.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -9,
                    label: __('baobab::admin.sidebar.widgets'),
                    icon: 'bi-grid',
                    url: route('admin.widgets.index'),
                    order: -18,
                );
            }

            if ($user->can('baobab.system.reading.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -10,
                    label: __('baobab::admin.sidebar.reading'),
                    icon: 'bi-book',
                    url: route('admin.reading.index'),
                    order: -19,
                );
            }

            if ($user->can('baobab.system.seo.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -11,
                    label: __('baobab::admin.sidebar.seo'),
                    icon: 'bi-globe2',
                    url: route('admin.seo.index'),
                    order: -14,
                );
            }

            if ($user->can('baobab.system.redirects.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -12,
                    label: __('baobab::admin.sidebar.redirects'),
                    icon: 'bi-signpost-split',
                    url: route('admin.redirects.index'),
                    order: -13,
                );
            }

            if ($user->can('baobab.system.api.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -13,
                    label: __('baobab::admin.sidebar.api'),
                    icon: 'bi-plug',
                    url: route('admin.api.index'),
                    order: -12,
                );
            }

            if ($user->can('baobab.system.webhooks.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -14,
                    label: __('baobab::admin.sidebar.webhooks'),
                    icon: 'bi-broadcast',
                    url: route('admin.webhooks.index'),
                    order: -11,
                );
            }

            if ($user->can('baobab.system.search.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -15,
                    label: __('baobab::admin.sidebar.search'),
                    icon: 'bi-search',
                    url: route('admin.search.index'),
                    order: -10,
                );
            }

            return $items->concat($coreItems);
        });
    }

    /**
     * Catalogue de champs Core, vague 1 (spec 02 §3.2). Un module peut en
     * ajouter d'autres via FieldRegistry::register() dans son provider.
     */
    private function registerCoreFieldTypes(): void
    {
        /** @var FieldRegistry $registry */
        $registry = $this->app->make(FieldRegistry::class);

        foreach ([
            TextField::class,
            TextareaField::class,
            RichTextField::class,
            SlugField::class,
            IntegerField::class,
            DecimalField::class,
            BooleanField::class,
            DateField::class,
            DateTimeField::class,
            TimeField::class,
            SelectField::class,
            MultiSelectField::class,
            RadioField::class,
            JsonField::class,
            ImageField::class,
            FileField::class,
            GalleryField::class,
        ] as $fieldType) {
            $registry->register($fieldType);
        }
    }

    /**
     * Widgets Core, v1 (spec 10 §3.2) — l'enregistrement de widgets fournis
     * par un module via son manifest n'est pas câblé : aucun mécanisme
     * générique manifest → classes n'existe encore dans ce code base,
     * indépendamment des widgets (suivi des différés).
     */
    private function registerCoreWidgets(): void
    {
        /** @var WidgetRegistry $registry */
        $registry = $this->app->make(WidgetRegistry::class);

        foreach ([
            RecentContentsWidget::class,
            MenuWidget::class,
            RichTextWidget::class,
            CustomHtmlWidget::class,
        ] as $widget) {
            $registry->register($widget);
        }
    }

    /**
     * Trois presets Core (spec 06 §4.1) : recadrage non destructif, ratios
     * conservés (`fit: contain`, jamais d'agrandissement). Modules/thèmes
     * ajoutent les leurs via `media_presets` de leur manifest, câblé aux
     * côtés des hooks dans bootstrapActiveModules().
     */
    private function registerCorePresets(): void
    {
        /** @var PresetRegistry $registry */
        $registry = $this->app->make(PresetRegistry::class);

        $registry->register('thumb', ['width' => 300, 'fit' => 'contain']);
        $registry->register('medium', ['width' => 768, 'fit' => 'contain']);
        $registry->register('large', ['width' => 1600, 'fit' => 'contain']);
    }

    /**
     * Trois sources Core (spec 11 §3.2), contexte `admin` uniquement pour
     * cette passe (moteur + indexation, M7 point 5 Pass A) — l'omnibox qui
     * les consomme réellement est Pass B.
     */
    private function registerCoreSearchSources(): void
    {
        /** @var SearchRegistry $registry */
        $registry = $this->app->make(SearchRegistry::class);

        foreach ([
            ContentsSearchSource::class,
            UsersSearchSource::class,
            MediaSearchSource::class,
        ] as $source) {
            $registry->register($source);
        }
    }

    /**
     * Guard `sanctum` (spec 08 §4.1, M7 point 2) — même patron d'injection
     * programmatique que `registerGuard()`, mais appelé depuis `boot()`
     * plutôt que `register()` : `SanctumServiceProvider::register()` doit
     * avoir fusionné son `config/sanctum.php` par défaut avant qu'on
     * écrase sa clé `guard` ici (seulement garanti une fois le `register()`
     * de tous les providers terminé, donc en `boot()`, jamais en
     * `register()` où l'ordre entre providers n'est pas maîtrisé).
     */
    private function registerSanctumGuard(): void
    {
        $this->app->make('config')->set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'baobab_users',
        ]);

        // Le repli « requête statefull » de Sanctum vérifie par défaut le
        // guard `web` (config('sanctum.guard'), ['web']) — jamais utilisé
        // par cette app, qui authentifie tout via son propre guard
        // `baobab`. Sans cette ligne, une session admin déjà connectée ne
        // serait jamais reconnue par le guard `sanctum`.
        $this->app->make('config')->set('sanctum.guard', ['baobab']);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    private function registerGuard(): void
    {
        /** @var string $userModel */
        $userModel = $this->app->make('config')->get(
            'baobab.auth.user_model',
            User::class,
        );

        $this->app->make('config')->set('auth.guards.baobab', [
            'driver' => 'session',
            'provider' => 'baobab_users',
        ]);

        $this->app->make('config')->set('auth.providers.baobab_users', [
            'driver' => 'eloquent',
            'model' => $userModel,
        ]);
    }

    /**
     * Journalisation technique (spec 12 §9) : channel dédié, isolé du `stack`
     * de l'application hôte — enregistré programmatiquement, comme le guard
     * et la config Spatie, pour ne rien exiger de la config publiée.
     */
    private function registerLogging(): void
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        $config->set('logging.channels.baobab', [
            'driver' => 'daily',
            'path' => storage_path('logs/baobab.log'),
            'level' => $config->get('logging.channels.stack.level', 'debug'),
            'days' => $config->get('baobab.logging.retention_days', 14),
        ]);
    }

    private function registerSpatieConfig(): void
    {
        $this->app->make('config')->set('permission.table_names', [
            'roles' => 'baobab_roles',
            'permissions' => 'baobab_permissions',
            'model_has_permissions' => 'baobab_model_has_permissions',
            'model_has_roles' => 'baobab_model_has_roles',
            'role_has_permissions' => 'baobab_role_has_permissions',
        ]);

        $this->app->make('config')->set('permission.column_names', [
            'role_pivot_key' => null,
            'permission_pivot_key' => null,
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);

        $this->app->make('config')->set('permission.teams', false);
        $this->app->make('config')->set('permission.use_passport_client_credentials', false);
        $this->app->make('config')->set('permission.display_permission_in_exception', false);
        $this->app->make('config')->set('permission.display_role_in_exception', false);
        $this->app->make('config')->set('permission.enable_wildcard_permission', false);
        $this->app->make('config')->set('permission.cache.expiration_time', \DateInterval::createFromDateString('24 hours'));
        $this->app->make('config')->set('permission.cache.key', 'spatie.permission.cache');
        $this->app->make('config')->set('permission.cache.store', 'default');
    }

    /**
     * For every active module (single DB query):
     *   1. Register its ServiceProvider if the class is autoloadable.
     *   2. Wire its manifest hooks.listens into the HookRegistry.
     *   3. Wire its manifest hooks.emits onto the webhook dispatch listener
     *      (spec 08 §5, M7 point 4 Pass A) — reuses this query rather than
     *      a second `Module::where('status', 'active')` pass.
     *
     * Skipped silently when the DB is unavailable or not yet migrated.
     * Any unexpected error is logged as a warning so it stays visible.
     */
    private function bootstrapActiveModules(): void
    {
        try {
            if (! Schema::hasTable('modules')) {
                return;
            }
        } catch (Throwable) {
            // DB not reachable yet (fresh install, offline test env, etc.)
            return;
        }

        try {
            /** @var HookRegistry $registry */
            $registry = $this->app->make(HookRegistry::class);
            $autoloader = $this->app->make(ModuleAutoloader::class);
            /** @var PresetRegistry $presets */
            $presets = $this->app->make(PresetRegistry::class);

            Module::where('status', 'active')->each(function (Module $module) use ($registry, $autoloader, $presets): void {
                $autoloader->registerFor($module);

                if (class_exists($module->provider)) {
                    $this->app->register($module->provider);
                }

                foreach ($module->manifest['hooks']['listens'] ?? [] as $hook => $listener) {
                    $registry->listen($hook, $listener);
                }

                foreach ($module->manifest['hooks']['emits'] ?? [] as $hook) {
                    $registry->listen($hook, fn (mixed ...$args) => $this->app->make(DispatchWebhookEvent::class)($hook, array_values($args)));
                }

                foreach ($module->manifest['media_presets'] ?? [] as $name => $definition) {
                    $presets->register($name, $definition);
                }
            });
        } catch (Throwable $e) {
            Log::warning('[Baobab] Could not bootstrap active modules: '.$e->getMessage());
        }
    }
}
