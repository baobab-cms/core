<?php

declare(strict_types=1);

namespace Baobab;

use Baobab\Access\AccessManager;
use Baobab\Access\Facades\Access;
use Baobab\Access\Http\Middleware\ImpersonationGuard;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Baobab\Admin\Sidebar\SidebarBuilder;
use Baobab\Admin\Sidebar\SidebarItem;
use Baobab\Audit\AuditLogger;
use Baobab\Auth\TwoFactorManager;
use Baobab\Console\Commands\ContentTypeBuildCommand;
use Baobab\Console\Commands\ContentTypeMakeCommand;
use Baobab\Console\Commands\HookListCommand;
use Baobab\Console\Commands\MediaRegenerateCommand;
use Baobab\Console\Commands\ModuleActivateCommand;
use Baobab\Console\Commands\ModuleDeactivateCommand;
use Baobab\Console\Commands\ModuleInstallCommand;
use Baobab\Console\Commands\ModuleListCommand;
use Baobab\Console\Commands\ModuleUninstallCommand;
use Baobab\Console\Commands\SuperAdminCommand;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Fields\Types\BooleanField;
use Baobab\ContentTypes\Fields\Types\DateField;
use Baobab\ContentTypes\Fields\Types\DateTimeField;
use Baobab\ContentTypes\Fields\Types\DecimalField;
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
use Baobab\Facades\Hook;
use Baobab\Hooks\HookRegistry;
use Baobab\Media\Conversions\PresetRegistry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Support\Logger as SupportLogger;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Mews\Purifier\PurifierServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Throwable;

class BaobabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baobab.php', 'baobab');

        $this->registerSpatieConfig();

        $this->app->register(PermissionServiceProvider::class);
        $this->app->register(PurifierServiceProvider::class);

        $this->app->singleton(HookRegistry::class);

        $this->app->singleton(AccessManager::class);

        $this->app->singleton(SidebarBuilder::class);

        $this->app->singleton(AuditLogger::class);

        $this->app->singleton(PermissionMatrixBuilder::class);

        $this->app->singleton(TwoFactorManager::class, fn () => new TwoFactorManager(new Google2FA));

        $this->app->singleton(SupportLogger::class);

        $this->app->singleton(FieldRegistry::class);

        $this->app->singleton(PresetRegistry::class);

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'baobab');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'baobab');
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

        $this->registerAuditListeners();

        $this->registerCoreSidebarItems();

        $this->registerCoreFieldTypes();

        $this->registerCorePresets();

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
            ]);
        }
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

    private function loadAdminRoutes(): void
    {
        Route::middleware(['web', 'auth:baobab', 'verified', 'can:baobab.admin.access', ImpersonationGuard::class])
            ->prefix($this->app->make('config')->get('baobab.admin.path', 'admin'))
            ->name('admin.')
            ->group(__DIR__.'/../routes/admin.php');
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
     * Le Core est son propre premier consommateur du hook d'extension de la
     * sidebar (spec 04 §3.3) : les écrans Audit/Accès ne viennent pas d'un
     * module, donc pas de module_menu_items — on les injecte comme le ferait
     * n'importe quel listener externe.
     */
    private function registerCoreSidebarItems(): void
    {
        Hook::listen('baobab.admin.menu', function (Collection $items, ?User $user) {
            if ($user === null) {
                return $items;
            }

            $coreItems = [];

            if ($user->can('baobab.access.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -1,
                    label: __('baobab::admin.sidebar.access'),
                    icon: null,
                    url: route('admin.access.index'),
                    order: -20,
                );
            }

            if ($user->can('baobab.audit.view')) {
                $coreItems[] = new SidebarItem(
                    id: -2,
                    label: __('baobab::admin.sidebar.audit'),
                    icon: null,
                    url: route('admin.audit.index'),
                    order: -10,
                );
            }

            if ($user->can('baobab.users.impersonate')) {
                $coreItems[] = new SidebarItem(
                    id: -3,
                    label: __('baobab::admin.sidebar.users'),
                    icon: null,
                    url: route('admin.users.index'),
                    order: -30,
                );
            }

            if ($user->can('baobab.media.view')) {
                $coreItems[] = new SidebarItem(
                    id: -4,
                    label: __('baobab::admin.sidebar.media'),
                    icon: null,
                    url: route('admin.media.index'),
                    order: -40,
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
        ] as $fieldType) {
            $registry->register($fieldType);
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

                foreach ($module->manifest['media_presets'] ?? [] as $name => $definition) {
                    $presets->register($name, $definition);
                }
            });
        } catch (Throwable $e) {
            Log::warning('[Baobab] Could not bootstrap active modules: '.$e->getMessage());
        }
    }
}
