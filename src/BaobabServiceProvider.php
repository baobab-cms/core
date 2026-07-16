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
use Baobab\Audit\AuditLogger;
use Baobab\Auth\TwoFactorManager;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Console\Commands\ContentPublishDueCommand;
use Baobab\Console\Commands\ContentPurgeTrashCommand;
use Baobab\Console\Commands\ContentTypeBuildCommand;
use Baobab\Console\Commands\ContentTypeMakeCommand;
use Baobab\Console\Commands\ContentUnpublishDueCommand;
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
use Baobab\Console\Commands\NotificationsPurgeCommand;
use Baobab\Console\Commands\NotifyTestCommand;
use Baobab\Console\Commands\SuperAdminCommand;
use Baobab\Console\Commands\ThemeActivateCommand;
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
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Notify\Notifier;
use Baobab\Rendering\PublicRouteRegistrar;
use Baobab\Scheduler\SchedulerRegistrar;
use Baobab\Support\Logger as SupportLogger;
use Baobab\Users\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Mews\Purifier\PurifierServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
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

        $this->configurePurifier();

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

        $this->registerBrandingComposer();

        $this->registerNotificationCenterComposer();

        $this->registerAuditListeners();

        $this->registerMediaUsageListener();

        $this->registerWorkflowNotificationListeners();

        $this->registerSecurityNotificationListeners();

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
            ]);
        }

        $this->registerMediaPurgeSchedule();

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

            if (ValidationQueueController::isVisibleTo($user)) {
                $coreItems[] = new SidebarItem(
                    id: -5,
                    label: __('baobab::admin.sidebar.review'),
                    icon: null,
                    url: route('admin.review.index'),
                    order: -25,
                );
            }

            if ($user->can('baobab.system.branding.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -6,
                    label: __('baobab::admin.sidebar.branding'),
                    icon: null,
                    url: route('admin.branding.index'),
                    order: -15,
                );
            }

            if ($user->can('baobab.system.themes.manage')) {
                $coreItems[] = new SidebarItem(
                    id: -7,
                    label: __('baobab::admin.sidebar.themes'),
                    icon: null,
                    url: route('admin.themes.index'),
                    order: -16,
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
