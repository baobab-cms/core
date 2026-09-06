<?php

declare(strict_types=1);

use Baobab\Admin\Access\Http\Controllers\AccessMatrixController;
use Baobab\Admin\Account\Http\Controllers\ApiTokenController;
use Baobab\Admin\Account\Http\Controllers\NotificationPreferencesController;
use Baobab\Admin\Account\Http\Controllers\SecurityController;
use Baobab\Admin\Api\Http\Controllers\ApiSettingsController;
use Baobab\Admin\Branding\Http\Controllers\BrandingController;
use Baobab\Admin\Branding\Http\Controllers\FontsController;
use Baobab\Admin\Content\Http\Controllers\ContentController;
use Baobab\Admin\Content\Http\Controllers\ContentTypesController;
use Baobab\Admin\Content\Http\Controllers\TrashController;
use Baobab\Admin\Content\Http\Controllers\ValidationQueueController;
use Baobab\Admin\Demo\Http\Controllers\DemoContentController;
use Baobab\Admin\Forms\Http\Controllers\FormsController;
use Baobab\Admin\Forms\Http\Controllers\FormSubmissionsController;
use Baobab\Admin\Mail\Http\Controllers\MailLogController;
use Baobab\Admin\Mail\Http\Controllers\MailTemplatesController;
use Baobab\Admin\Media\Http\Controllers\MediaController;
use Baobab\Admin\Media\Http\Controllers\MediaFolderController;
use Baobab\Admin\Menus\Http\Controllers\MenusController;
use Baobab\Admin\Modules\Http\Controllers\ModulesController;
use Baobab\Admin\Notifications\Http\Controllers\NotificationController;
use Baobab\Admin\Rendering\Http\Controllers\ReadingSettingsController;
use Baobab\Admin\Search\Http\Controllers\OmniboxController;
use Baobab\Admin\Search\Http\Controllers\SearchSettingsController;
use Baobab\Admin\Seo\Http\Controllers\NotFoundLogController;
use Baobab\Admin\Seo\Http\Controllers\RedirectsController;
use Baobab\Admin\Seo\Http\Controllers\SeoSettingsController;
use Baobab\Admin\Studio\Http\Controllers\StudioController;
use Baobab\Admin\Themes\Http\Controllers\ThemeBlueprintController;
use Baobab\Admin\Themes\Http\Controllers\ThemesController;
use Baobab\Admin\Users\Http\Controllers\ImpersonationController;
use Baobab\Admin\Users\Http\Controllers\UserController;
use Baobab\Admin\Webhooks\Http\Controllers\WebhookDeliveriesController;
use Baobab\Admin\Webhooks\Http\Controllers\WebhookSubscriptionsController;
use Baobab\Admin\Widgets\Http\Controllers\WidgetsController;
use Baobab\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('baobab::admin.dashboard'))->name('dashboard');

Route::prefix('account/security')
    ->name('account.security.')
    ->group(function (): void {
        Route::get('/', [SecurityController::class, 'show'])->name('show');
        Route::post('/enable', [SecurityController::class, 'enable'])->name('enable');
        Route::post('/confirm', [SecurityController::class, 'confirm'])->name('confirm');
        Route::post('/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
        Route::post('/disable', [SecurityController::class, 'disable'])->name('disable');
    });

Route::prefix('account/notifications')
    ->name('account.notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationPreferencesController::class, 'show'])->name('show');
        Route::post('/', [NotificationPreferencesController::class, 'update'])->name('update');
    });

Route::prefix('account/api-tokens')
    ->name('account.api-tokens.')
    ->group(function (): void {
        Route::get('/', [ApiTokenController::class, 'index'])->name('index');
        Route::post('/', [ApiTokenController::class, 'store'])->name('store');
        Route::delete('/{token}', [ApiTokenController::class, 'destroy'])->name('destroy');
    });

Route::prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/poll', [NotificationController::class, 'poll'])->name('poll');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    });

Route::middleware('can:baobab.audit.view')
    ->prefix('audit')
    ->name('audit.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
    });

Route::middleware('can:baobab.access.manage')
    ->prefix('access')
    ->name('access.')
    ->group(function (): void {
        Route::get('/', [AccessMatrixController::class, 'index'])->name('index');
        Route::post('/roles', [AccessMatrixController::class, 'store'])->name('roles.store');
        Route::post('/{role}/permissions/{permission}', [AccessMatrixController::class, 'toggle'])->name('toggle');
    });

Route::middleware('can:baobab.system.branding.manage')
    ->prefix('branding')
    ->name('branding.')
    ->group(function (): void {
        Route::get('/', [BrandingController::class, 'index'])->name('index');
        Route::post('/', [BrandingController::class, 'update'])->name('update');
        Route::post('/profile', [BrandingController::class, 'applyProfile'])->name('profile');
        Route::post('/reset-token/{group}/{key}', [BrandingController::class, 'resetToken'])->name('reset-token');
        Route::post('/reset', [BrandingController::class, 'resetTokens'])->name('reset');
    });

Route::middleware('can:baobab.system.fonts.manage')
    ->prefix('branding/fonts')
    ->name('branding.fonts.')
    ->group(function (): void {
        Route::post('/', [FontsController::class, 'store'])->name('store');
        Route::delete('/{font}', [FontsController::class, 'destroy'])->name('destroy');
    });

Route::middleware('can:baobab.system.reading.manage')
    ->prefix('reading')
    ->name('reading.')
    ->group(function (): void {
        Route::get('/', [ReadingSettingsController::class, 'index'])->name('index');
        Route::post('/', [ReadingSettingsController::class, 'update'])->name('update');
    });

Route::middleware('can:baobab.system.api.manage')
    ->prefix('api')
    ->name('api.')
    ->group(function (): void {
        Route::get('/', [ApiSettingsController::class, 'index'])->name('index');
        Route::post('/', [ApiSettingsController::class, 'update'])->name('update');
    });

Route::middleware('can:baobab.system.seo.manage')
    ->prefix('seo')
    ->name('seo.')
    ->group(function (): void {
        Route::get('/', [SeoSettingsController::class, 'index'])->name('index');
        Route::post('/', [SeoSettingsController::class, 'update'])->name('update');
    });

// Omnibox (spec 11 §4.1) — nommée admin.omnibox.*, hors du préfixe
// admin.search.* bloqué en impersonation : chercher reste permis, les
// résultats étant bornés par les policies de l'acteur impersoné.
Route::get('/omnibox/search', [OmniboxController::class, 'search'])->name('omnibox.search');

Route::middleware('can:baobab.system.search.manage')
    ->prefix('search')
    ->name('search.')
    ->group(function (): void {
        Route::get('/', [SearchSettingsController::class, 'index'])->name('index');
        Route::post('/reindex', [SearchSettingsController::class, 'reindex'])->name('reindex');
    });

Route::middleware('can:baobab.system.redirects.manage')
    ->prefix('redirects')
    ->name('redirects.')
    ->group(function (): void {
        Route::get('/', [RedirectsController::class, 'index'])->name('index');
        Route::get('/create', [RedirectsController::class, 'create'])->name('create');
        Route::post('/', [RedirectsController::class, 'store'])->name('store');
        Route::get('/export', [RedirectsController::class, 'export'])->name('export');
        Route::post('/import', [RedirectsController::class, 'import'])->name('import');
        Route::get('/not-found', [NotFoundLogController::class, 'index'])->name('not-found');
        Route::get('/{redirect}', [RedirectsController::class, 'edit'])->name('edit');
        Route::put('/{redirect}', [RedirectsController::class, 'update'])->name('update');
        Route::delete('/{redirect}', [RedirectsController::class, 'destroy'])->name('destroy');
    });

Route::middleware('can:baobab.system.webhooks.manage')
    ->prefix('webhooks')
    ->name('webhooks.')
    ->group(function (): void {
        Route::get('/', [WebhookSubscriptionsController::class, 'index'])->name('index');
        Route::get('/create', [WebhookSubscriptionsController::class, 'create'])->name('create');
        Route::post('/', [WebhookSubscriptionsController::class, 'store'])->name('store');
        Route::get('/{subscription}', [WebhookSubscriptionsController::class, 'edit'])->name('edit');
        Route::put('/{subscription}', [WebhookSubscriptionsController::class, 'update'])->name('update');
        Route::delete('/{subscription}', [WebhookSubscriptionsController::class, 'destroy'])->name('destroy');
        Route::get('/{subscription}/deliveries', [WebhookDeliveriesController::class, 'index'])->name('deliveries.index');
        Route::post('/deliveries/{delivery}/redeliver', [WebhookDeliveriesController::class, 'redeliver'])->name('deliveries.redeliver');
    });

Route::middleware('can:baobab.system.forms.manage')
    ->prefix('forms')
    ->name('forms.')
    ->group(function (): void {
        Route::get('/', [FormsController::class, 'index'])->name('index');
        Route::get('/create', [FormsController::class, 'create'])->name('create');
        Route::post('/', [FormsController::class, 'store'])->name('store');
        Route::post('/import', [FormsController::class, 'import'])->name('import');
        Route::get('/{form}', [FormsController::class, 'edit'])->name('edit');
        Route::put('/{form}', [FormsController::class, 'update'])->name('update');
        Route::put('/{form}/settings', [FormsController::class, 'updateSettings'])->name('settings.update');
        Route::get('/{form}/export', [FormsController::class, 'export'])->name('export');
        Route::delete('/{form}', [FormsController::class, 'destroy'])->name('destroy');
    });

Route::prefix('forms/{form}/submissions')
    ->name('forms.submissions.')
    ->group(function (): void {
        Route::get('/', [FormSubmissionsController::class, 'index'])
            ->middleware('can:baobab.system.forms.submissions_view')->name('index');
        Route::get('/export', [FormSubmissionsController::class, 'export'])
            ->middleware('can:baobab.system.forms.submissions_export')->name('export');
        Route::get('/{submission}', [FormSubmissionsController::class, 'show'])
            ->middleware('can:baobab.system.forms.submissions_view')->name('show');
        Route::post('/{submission}/status', [FormSubmissionsController::class, 'markStatus'])
            ->middleware('can:baobab.system.forms.submissions_view')->name('mark-status');
        Route::delete('/{submission}', [FormSubmissionsController::class, 'destroy'])
            ->middleware('can:baobab.system.forms.submissions_delete')->name('destroy');
        // Signée ET permissionnée (spec 14 §5) — aucune autre route du Core
        // ne combine les deux (Pass C3, patron de signature seule :
        // routes/theme-preview.php).
        Route::get('/{submission}/files/{field}', [FormSubmissionsController::class, 'downloadFile'])
            ->middleware(['can:baobab.system.forms.submissions_view', 'signed'])->name('files.show');
    });

Route::middleware('can:baobab.system.themes.manage')
    ->prefix('themes')
    ->name('themes.')
    ->group(function (): void {
        Route::get('/', [ThemesController::class, 'index'])->name('index');

        Route::prefix('studio')
            ->name('studio.')
            ->group(function (): void {
                Route::get('/', [ThemeBlueprintController::class, 'index'])->name('index');
                Route::get('/create', [ThemeBlueprintController::class, 'create'])->name('create');
                Route::post('/', [ThemeBlueprintController::class, 'store'])->name('store');
                Route::get('/{slug}', [ThemeBlueprintController::class, 'edit'])->name('edit');
                Route::post('/{slug}', [ThemeBlueprintController::class, 'update'])->name('update');
            });

        Route::post('/{theme}/activate', [ThemesController::class, 'activate'])->name('activate');
        Route::post('/{theme}/deactivate', [ThemesController::class, 'deactivate'])->name('deactivate');
        Route::get('/{theme}/preview', [ThemesController::class, 'preview'])->name('preview');
    });

Route::middleware('can:baobab.menus.manage')
    ->prefix('menus')
    ->name('menus.')
    ->group(function (): void {
        Route::get('/', [MenusController::class, 'index'])->name('index');
        Route::get('/create', [MenusController::class, 'create'])->name('create');
        Route::post('/', [MenusController::class, 'store'])->name('store');
        Route::get('/search-content', [MenusController::class, 'searchContent'])->name('search-content');
        Route::get('/{menu}', [MenusController::class, 'edit'])->name('edit');
        Route::post('/{menu}', [MenusController::class, 'update'])->name('update');
        Route::delete('/{menu}', [MenusController::class, 'destroy'])->name('destroy');
    });

Route::middleware('can:baobab.widgets.manage')
    ->prefix('widgets')
    ->name('widgets.')
    ->group(function (): void {
        Route::get('/', [WidgetsController::class, 'index'])->name('index');
        Route::get('/create', [WidgetsController::class, 'create'])->name('create');
        Route::post('/', [WidgetsController::class, 'store'])->name('store');
        Route::get('/{instance}/edit', [WidgetsController::class, 'edit'])->name('edit');
        Route::post('/{instance}', [WidgetsController::class, 'update'])->name('update');
        Route::delete('/{instance}', [WidgetsController::class, 'destroy'])->name('destroy');
        Route::post('/{instance}/move-up', [WidgetsController::class, 'moveUp'])->name('move-up');
        Route::post('/{instance}/move-down', [WidgetsController::class, 'moveDown'])->name('move-down');
    });

Route::middleware('can:baobab.system.studio.manage')
    ->prefix('studio')
    ->name('studio.')
    ->group(function (): void {
        Route::get('/', [StudioController::class, 'index'])->name('index');
        Route::get('/create', [StudioController::class, 'create'])->name('create');
        Route::post('/', [StudioController::class, 'store'])->name('store');
        Route::get('/{draft}', [StudioController::class, 'show'])->name('show');
        Route::delete('/{draft}', [StudioController::class, 'destroy'])->name('destroy');
        Route::get('/{draft}/step/{step}', [StudioController::class, 'stepShow'])->whereNumber('step')->name('step.show');
        Route::post('/{draft}/step/{step}', [StudioController::class, 'stepUpdate'])->whereNumber('step')->name('step.update');
        // Générer n'est pas enregistrer une étape : route distincte du cycle
        // `step.update`, qui ne fait qu'écrire dans le blueprint (Pass B5).
        Route::post('/{draft}/generate', [StudioController::class, 'generate'])->name('generate');
        // Seconde sortie du Studio (spec-modules §5.3) : l'archive se construit
        // depuis le plan, sans passer par `/modules` — donc disponible qu'un
        // brouillon ait été généré ou non.
        Route::get('/{draft}/download', [StudioController::class, 'download'])->name('download');
        Route::get('/{draft}/conflicts', [StudioController::class, 'conflicts'])->name('conflicts');
    });

// Cycle de vie générique des modules (spec-modules §3, M8 point 9). Le nom d'un
// module est `vendor/slug` : deux segments plutôt qu'un paramètre à barre
// oblique échappée. La contrainte de route reste large — c'est l'inventaire qui
// décide de l'existence, pas une regex d'URL.
Route::middleware('can:baobab.system.modules.manage')
    ->prefix('modules')
    ->name('modules.')
    ->group(function (): void {
        Route::get('/', [ModulesController::class, 'index'])->name('index');
        Route::post('/upload', [ModulesController::class, 'upload'])->name('upload');

        Route::prefix('/{vendor}/{slug}')
            ->where(['vendor' => '[a-z0-9._-]+', 'slug' => '[a-z0-9._-]+'])
            ->group(function (): void {
                Route::post('/install', [ModulesController::class, 'install'])->name('install');
                Route::post('/activate', [ModulesController::class, 'activate'])->name('activate');
                Route::post('/deactivate', [ModulesController::class, 'deactivate'])->name('deactivate');
                Route::post('/update', [ModulesController::class, 'update'])->name('update');
                Route::delete('/', [ModulesController::class, 'uninstall'])->name('uninstall');
            });
    });

Route::middleware('can:baobab.system.demo_content.manage')
    ->prefix('demo-content')
    ->name('demo-content.')
    ->group(function (): void {
        Route::get('/', [DemoContentController::class, 'index'])->name('index');
        Route::delete('/', [DemoContentController::class, 'destroy'])->name('destroy');
    });

Route::middleware('can:baobab.users.impersonate')
    ->prefix('users')
    ->name('users.')
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/{user}/impersonate', [ImpersonationController::class, 'store'])->name('impersonate');
    });

Route::prefix('media')
    ->name('media.')
    ->group(function (): void {
        Route::get('/', [MediaController::class, 'index'])->name('index');
        Route::post('/', [MediaController::class, 'store'])->name('store');
        Route::post('/chunk', [MediaController::class, 'storeChunk'])->name('chunk');
        Route::post('/external', [MediaController::class, 'storeExternal'])->name('external.store');
        Route::post('/move', [MediaController::class, 'move'])->name('move');
        Route::post('/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::post('/bulk-restore', [MediaController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-force-destroy', [MediaController::class, 'bulkForceDestroy'])->name('bulk-force-destroy');
        Route::post('/folders', [MediaFolderController::class, 'store'])->name('folders.store');
        Route::put('/folders/{folder}', [MediaFolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [MediaFolderController::class, 'destroy'])->name('folders.destroy');
        Route::get('/{media}', [MediaController::class, 'show'])->name('show');
        Route::patch('/{media}', [MediaController::class, 'update'])->name('update');
        Route::patch('/{media}/focal-point', [MediaController::class, 'updateFocalPoint'])->name('focal-point.update');
        Route::post('/{media}/transform', [MediaController::class, 'storeTransform'])->name('transform.store');
        Route::delete('/{media}/transform', [MediaController::class, 'destroyTransform'])->name('transform.destroy');
        Route::post('/{media}/restore', [MediaController::class, 'restore'])->name('restore')->withTrashed();
        Route::delete('/{media}/force', [MediaController::class, 'forceDestroy'])->name('force-destroy')->withTrashed();
        Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
    });

Route::get('trash', [TrashController::class, 'index'])->name('trash.index');

Route::get('review', [ValidationQueueController::class, 'index'])->name('review.index');

// Le Content Type builder (spec 02 §2.1, M8 point 2) — le *schéma*, pas les
// entrées. Déclaré avant `content/{contentType}` : `content-types` est un
// segment distinct, mais garder les deux voisins dit qu'il s'agit du même
// domaine vu à deux étages.
Route::middleware('can:baobab.system.content_types.manage')
    ->prefix('content-types')
    ->name('content-types.')
    ->group(function (): void {
        Route::get('/', [ContentTypesController::class, 'index'])->name('index');
        Route::get('/create', [ContentTypesController::class, 'create'])->name('create');
        Route::post('/', [ContentTypesController::class, 'store'])->name('store');
        // Liaison par `key` et non par `id` : c'est la clé technique qui
        // identifie un type partout ailleurs (URL des entrées, permissions,
        // nom de table), et elle est unique.
        Route::get('/{contentType:key}/edit', [ContentTypesController::class, 'edit'])->name('edit');
        Route::put('/{contentType:key}', [ContentTypesController::class, 'update'])->name('update');
    });

Route::prefix('content/{contentType}')
    ->name('content.')
    ->group(function (): void {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::get('/create', [ContentController::class, 'create'])->name('create');
        Route::post('/', [ContentController::class, 'store'])->name('store');
        Route::post('/bulk-delete', [ContentController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::post('/bulk-restore', [ContentController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/bulk-force-destroy', [ContentController::class, 'bulkForceDestroy'])->name('bulk-force-destroy');
        Route::get('/{entry}/edit', [ContentController::class, 'edit'])->name('edit');
        Route::put('/{entry}', [ContentController::class, 'update'])->name('update');
        Route::delete('/{entry}', [ContentController::class, 'destroy'])->name('destroy');
        Route::post('/{entry}/restore', [ContentController::class, 'restore'])->name('restore');
        Route::delete('/{entry}/force', [ContentController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{entry}/transition/{transition}', [ContentController::class, 'transition'])->name('transition');
        Route::get('/{entry}/revisions', [ContentController::class, 'revisions'])->name('revisions');
        Route::post('/{entry}/revisions/{revision}/restore', [ContentController::class, 'restoreRevision'])->name('revisions.restore');
        Route::post('/{entry}/working-draft/publish', [ContentController::class, 'publishWorkingDraft'])->name('working-draft.publish');
        Route::post('/{entry}/working-draft/discard', [ContentController::class, 'discardWorkingDraft'])->name('working-draft.discard');
        Route::post('/{entry}/working-draft/submit', [ContentController::class, 'submitWorkingDraft'])->name('working-draft.submit');
        Route::post('/{entry}/working-draft/approve', [ContentController::class, 'approveWorkingDraft'])->name('working-draft.approve');
        Route::post('/{entry}/working-draft/reject', [ContentController::class, 'rejectWorkingDraft'])->name('working-draft.reject');
        Route::post('/{entry}/autosave', [ContentController::class, 'autosave'])->name('autosave');
        Route::post('/{entry}/lock/heartbeat', [ContentController::class, 'heartbeat'])->name('lock.heartbeat');
        Route::post('/{entry}/lock/release', [ContentController::class, 'releaseLock'])->name('lock.release');
        Route::post('/{entry}/lock/take-over', [ContentController::class, 'takeOverLock'])->name('lock.take-over');
    });

// Personnalisation des templates d'e-mails (spec 13 §3.2-3.4, M8 point 7,
// Pass A2). Liaison par la clé du template et non par l'id de la ligne
// `mail_templates` : un template non personnalisé n'a pas de ligne du tout,
// et c'est pourtant lui qu'on vient éditer en premier.
Route::middleware('can:baobab.system.mail.templates')
    ->prefix('mails')
    ->name('mails.')
    ->group(function (): void {
        Route::get('/', [MailTemplatesController::class, 'index'])->name('index');
        Route::get('/{key}/edit', [MailTemplatesController::class, 'edit'])->name('edit');
        // `POST` et non `PUT`, contrairement aux autres écrans d'édition : ce
        // formulaire a **deux** cibles (enregistrer, et prévisualiser via
        // `formaction`), or le `_method` posé par le method spoofing vaut pour
        // le formulaire entier — le bouton d'aperçu partait donc en `PUT` vers
        // une route qui ne l'accepte pas. Défaut trouvé en vérification
        // navigateur ; ne pas spoofer est la seule issue sans JavaScript.
        Route::post('/{key}', [MailTemplatesController::class, 'update'])->name('update');
        Route::post('/{key}/restore', [MailTemplatesController::class, 'restore'])->name('restore');
        Route::post('/{key}/preview', [MailTemplatesController::class, 'preview'])->name('preview');
        Route::post('/{key}/test', [MailTemplatesController::class, 'test'])->name('test');
    });

// Journal des e-mails (spec 13 §4.2, M8 point 7, Pass B2). **Groupe séparé
// malgré le préfixe commun** : la spec fait de `.log_view` et `.templates`
// deux permissions distinctes, et quelqu'un peut consulter ce qui est parti
// sans pouvoir réécrire les templates. Les partager mettrait la seconde en
// condition de la première.
//
// À `mails/log` et non à l'`admin/system/mail-log` de la spec : aucun
// `admin/system/*` n'existe dans le Core, et les deux autres journaux du
// produit sont eux aussi des sous-écrans de leur section — `redirects/
// not-found`, `webhooks/{id}/deliveries`. Adresse tranchée et spec amendée
// le 24 août 2026 (suivi n° 197).
Route::middleware('can:baobab.system.mail.log_view')
    ->prefix('mails')
    ->name('mails.')
    ->group(function (): void {
        Route::get('/log', [MailLogController::class, 'index'])->name('log');
        Route::post('/log/{entry}/resend', [MailLogController::class, 'resend'])
            ->middleware('can:baobab.system.mail.resend')
            ->name('log.resend');
    });
