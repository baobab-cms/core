<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\AcquireOrRefreshContentLock;
use Baobab\ContentTypes\Actions\ApproveContentEntry;
use Baobab\ContentTypes\Actions\ApproveWorkingDraftReview;
use Baobab\ContentTypes\Actions\ArchiveContentEntry;
use Baobab\ContentTypes\Actions\AutosaveContentEntry;
use Baobab\ContentTypes\Actions\DeleteContentEntry;
use Baobab\ContentTypes\Actions\DiscardWorkingDraftEntry;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\PublishWorkingDraftEntry;
use Baobab\ContentTypes\Actions\PurgeContentEntry;
use Baobab\ContentTypes\Actions\RejectContentEntry;
use Baobab\ContentTypes\Actions\RejectWorkingDraftReview;
use Baobab\ContentTypes\Actions\ReleaseContentLock;
use Baobab\ContentTypes\Actions\RestoreArchivedContentEntry;
use Baobab\ContentTypes\Actions\RestoreContentEntryFromTrash;
use Baobab\ContentTypes\Actions\RestoreContentRevision;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Actions\SaveWorkingDraftEntry;
use Baobab\ContentTypes\Actions\ScheduleContentEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
use Baobab\ContentTypes\Actions\SubmitWorkingDraftForReview;
use Baobab\ContentTypes\Actions\TakeOverContentLock;
use Baobab\ContentTypes\Actions\UnpublishContentEntry;
use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Editorial\Support\RevisionDiffer;
use Baobab\ContentTypes\Exceptions\ContentLockedException;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\BelongsToRelations;
use Baobab\ContentTypes\Relations\RelationOptions;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\ContentTypes\Support\ContentEntryRules;
use Baobab\ContentTypes\Support\ContentTrash;
use Baobab\ContentTypes\Support\FieldDisplay;
use Baobab\Facades\Hook;
use Baobab\Forms\Models\Form;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * CRUD admin auto-généré pour tout Content Type construit (spec 02 §6, M3
 * point 5). Un seul contrôleur pilote toutes les instances : le Content Type
 * est résolu dynamiquement depuis `{contentType}` (slug de route), jamais un
 * contrôleur par type généré — cohérent avec le routage à jeu de routes
 * statique décidé dans le plan de ce point.
 */
final class ContentController
{
    public function __construct(
        private readonly ContentStateMachine $machine,
        private readonly ContentEntryRules $entryRules,
    ) {}

    public function index(Request $request, string $contentType): View
    {
        $type = $this->resolveContentType($contentType);
        $this->authorizeClass('viewAny', $type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        $trashed = $request->boolean('trashed');

        $query = $trashed ? ContentTrash::onlyTrashed($modelClass) : $modelClass::query();

        if (! $trashed) {
            $this->applySearch($query, $type, (string) $request->string('q'));

            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->toString());
            }
        }

        /** @var LengthAwarePaginator<int, Model> $rows */
        $rows = $query->orderByDesc('id')->paginate(20)->withQueryString();

        /** @var list<string> $statuses */
        $statuses = $trashed ? [] : $modelClass::query()->distinct()->pluck('status')->filter()->values()->all();

        $actor = $this->actor();
        $permissionPrefix = 'content.'.Str::snake($type->key);
        $canBulkDelete = $actor->can("{$permissionPrefix}.delete_any") || $actor->can("{$permissionPrefix}.delete");
        $canPurge = $actor->can('baobab.trash.purge');

        return view('baobab::admin.content.index', [
            'contentType' => $type,
            'slug' => $contentType,
            'pageTitle' => $this->pluralLabel($type),
            'columns' => $this->listColumns($type, $contentType, $trashed),
            'rows' => $rows,
            'statuses' => $statuses,
            'trashed' => $trashed,
            'canCreate' => $actor->can('create', $modelClass),
            'canPurge' => $canPurge,
            'bulkActions' => $this->indexBulkActions($contentType, $trashed, $canBulkDelete, $canPurge),
        ]);
    }

    /**
     * @return list<array{route: string, label: string}>
     */
    private function indexBulkActions(string $contentType, bool $trashed, bool $canBulkDelete, bool $canPurge): array
    {
        if ($trashed) {
            $actions = [
                ['route' => route('admin.content.bulk-restore', ['contentType' => $contentType]), 'label' => __('baobab::admin.content.bulk_restore_action')],
            ];

            if ($canPurge) {
                $actions[] = ['route' => route('admin.content.bulk-force-destroy', ['contentType' => $contentType]), 'label' => __('baobab::admin.content.bulk_purge_action')];
            }

            return $actions;
        }

        return $canBulkDelete ? [
            ['route' => route('admin.content.bulk-delete', ['contentType' => $contentType]), 'label' => __('baobab::admin.content.bulk_delete_action')],
        ] : [];
    }

    public function create(string $contentType): View
    {
        $type = $this->resolveContentType($contentType);
        $this->authorizeClass('create', $type);

        return view('baobab::admin.content.form', [
            'contentType' => $type,
            'slug' => $contentType,
            'label' => $this->label($type),
            'isEdit' => false,
            'editFormConfig' => null,
            'formMethod' => 'POST',
            'formAction' => route('admin.content.store', ['contentType' => $contentType]),
            'fields' => $this->formFieldsForView($type, null),
            'sections' => $this->formSections($type, null),
            'embeddableForms' => $this->embeddableForms(),
        ]);
    }

    public function store(Request $request, string $contentType): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $this->authorizeClass('create', $type);

        $validated = $this->validated($request, $type);

        app(SaveContentEntry::class)($type, $validated, $this->actor());

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.created')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType]);
    }

    public function edit(string $contentType, int|string $entry): View
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        $actor = $this->actor();
        $prefix = 'content.'.Str::snake($type->key);
        $status = (string) $model->getAttribute('status');

        $lockedBy = null;

        try {
            app(AcquireOrRefreshContentLock::class)($model, $actor);
        } catch (ContentLockedException $e) {
            $lockedBy = $e->getMessage();
        }

        $workingDraft = $this->workingDraftFor($model);
        $workingDraftPending = $workingDraft?->type === 'pending';
        $autosave = $this->autosaveFor($model, $actor, $workingDraft);

        return view('baobab::admin.content.form', [
            'contentType' => $type,
            'slug' => $contentType,
            'label' => $this->label($type),
            'isEdit' => true,
            'editFormConfig' => $this->editFormConfig($contentType, $model->getKey(), $lockedBy !== null),
            'formMethod' => 'PUT',
            'formAction' => route('admin.content.update', ['contentType' => $contentType, 'entry' => $model->getKey()]),
            'fields' => $this->formFieldsForView($type, $model),
            'sections' => $this->formSections($type, $model),
            'embeddableForms' => $this->embeddableForms(),
            'entryId' => $model->getKey(),
            'currentStatus' => $status,
            'publishedAt' => $model->getAttribute('published_at'),
            'availableTransitions' => $this->machine->availableTransitions($type, $status),
            'canUpdate' => $actor->can('update', $model),
            'canPublish' => $actor->can('publish', $model),
            'canPublishAny' => $actor->can("{$prefix}.publish_any"),
            'canTakeOverLock' => $actor->can("{$prefix}.update_any"),
            'lockedBy' => $lockedBy,
            'readOnly' => $lockedBy !== null,
            'canSaveAsDraft' => in_array($status, ['published', 'scheduled'], true) && $actor->can('update', $model),
            'workingDraft' => $workingDraft,
            'workingDraftPending' => $workingDraftPending,
            'workingDraftDiff' => $workingDraftPending
                ? app(RevisionDiffer::class)->diff($model->attributesToArray(), $workingDraft->snapshot)
                : null,
            'autosave' => $autosave,
            'reviewHistory' => $this->reviewHistoryFor($model),
        ]);
    }

    /**
     * Fil des allers-retours du workflow de validation (spec 09 §5 :
     * « historique des allers-retours »), soumission native comme brouillon
     * de contenu publié — pas de table dédiée, l'audit log porte déjà
     * l'acteur, la date et le commentaire de chaque étape.
     *
     * @return Collection<int, AuditEntry>
     */
    private function reviewHistoryFor(Model $entry): Collection
    {
        return AuditEntry::where('auditable_type', $entry->getMorphClass())
            ->where('auditable_id', $entry->getKey())
            ->whereIn('action', [
                'content.submitted',
                'content.approved',
                'content.rejected',
                'content.working_draft.submitted',
                'content.working_draft.approved',
                'content.working_draft.rejected',
            ])
            ->orderByDesc('created_at')
            ->with('actor')
            ->get();
    }

    private function workingDraftFor(Model $entry): ?Revision
    {
        return Revision::where('revisionable_type', $entry->getMorphClass())
            ->where('revisionable_id', $entry->getKey())
            ->whereIn('type', ['working_draft', 'pending'])
            ->first();
    }

    /**
     * Ne propose la restauration d'un autosave que s'il est plus récent que
     * le brouillon en cours (sinon rien de nouveau à récupérer).
     */
    private function autosaveFor(Model $entry, User $actor, ?Revision $workingDraft): ?Revision
    {
        $autosave = Revision::where('revisionable_type', $entry->getMorphClass())
            ->where('revisionable_id', $entry->getKey())
            ->where('author_id', $actor->getKey())
            ->where('type', 'autosave')
            ->first();

        if ($autosave === null) {
            return null;
        }

        if ($workingDraft !== null && $autosave->updated_at->lessThanOrEqualTo($workingDraft->updated_at)) {
            return null;
        }

        return $autosave;
    }

    /**
     * Machine à états éditoriale (spec 09 §2.2, M5 point 1) : un seul
     * endpoint pour les 8 transitions nommées, permission vérifiée ici selon
     * la table exacte de la spec — `approve`/`reject` exigent toujours
     * `publish_any` (jamais own, contrairement à `publish`/`schedule`/
     * `unpublish` qui réutilisent la policy `publish` générée, own/any).
     */
    public function transition(Request $request, string $contentType, int|string $entry, string $transition): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();
        $prefix = 'content.'.Str::snake($type->key);

        match ($transition) {
            'submit', 'archive', 'restore' => abort_unless($actor->can('update', $model), 403),
            'approve', 'reject' => abort_unless($actor->can("{$prefix}.publish_any"), 403),
            'publish', 'schedule', 'unpublish' => abort_unless($actor->can('publish', $model), 403),
            default => abort(404),
        };

        try {
            match ($transition) {
                'submit' => app(SubmitContentEntry::class)($type, $model, $actor),
                'approve' => app(ApproveContentEntry::class)($type, $model, $this->nullableFutureDate($request), $actor),
                'reject' => app(RejectContentEntry::class)(
                    $type,
                    $model,
                    $request->validate(['comment' => ['required', 'string', 'min:3']])['comment'],
                    $actor,
                ),
                'publish' => app(PublishContentEntry::class)($type, $model, $actor),
                'schedule' => app(ScheduleContentEntry::class)(
                    $type,
                    $model,
                    Carbon::parse($request->validate(['published_at' => ['required', 'date', 'after:now']])['published_at']),
                    $actor,
                ),
                'unpublish' => app(UnpublishContentEntry::class)($type, $model, $actor),
                'archive' => app(ArchiveContentEntry::class)($type, $model, $actor),
                'restore' => app(RestoreArchivedContentEntry::class)($type, $model, $actor),
            };
        } catch (InvalidContentTransitionException $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __("baobab::admin.content.transition_{$transition}_success")]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    private function nullableFutureDate(Request $request): ?Carbon
    {
        $value = $request->validate(['published_at' => ['nullable', 'date', 'after:now']])['published_at'] ?? null;

        return $value === null ? null : Carbon::parse($value);
    }

    public function update(Request $request, string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        $actor = $this->actor();

        try {
            app(AcquireOrRefreshContentLock::class)($model, $actor);
        } catch (ContentLockedException $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        $validated = $this->validated($request, $type, $model);

        $status = (string) $model->getAttribute('status');
        $wantsDraft = $request->string('intent')->toString() === 'draft';

        if ($wantsDraft && in_array($status, ['published', 'scheduled'], true)) {
            app(SaveWorkingDraftEntry::class)($type, $model, $validated, $actor);

            session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_saved')]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        app(SaveContentEntry::class)($type, $validated, $actor, $model);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.updated')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType]);
    }

    public function destroy(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('delete', $model);

        app(DeleteContentEntry::class)($type, $model);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.deleted')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType]);
    }

    public function bulkDestroy(Request $request, string $contentType): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        foreach ($ids as $id) {
            $model = $this->findEntry($type, $id);

            if ($this->actor()->can('delete', $model)) {
                app(DeleteContentEntry::class)($type, $model);
            }
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.deleted')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType]);
    }

    /**
     * Écran de révisions (spec 09 §6) : historique manuel/pre_restore (pas
     * l'autosave, invisible de l'historique éditorial) + diff optionnel entre
     * deux révisions choisies via `?from=&to=`.
     */
    public function revisions(Request $request, string $contentType, int|string $entry): View
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        $history = Revision::where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->whereIn('type', ['manual', 'pre_restore'])
            ->orderByDesc('id')
            ->with('author')
            ->get();

        $diff = null;
        $fromId = $request->integer('from');
        $toId = $request->integer('to');

        if ($fromId !== 0 && $toId !== 0) {
            $from = $history->firstWhere('id', $fromId);
            $to = $history->firstWhere('id', $toId);

            if ($from !== null && $to !== null) {
                $diff = app(RevisionDiffer::class)->diff($from->snapshot, $to->snapshot);
            }
        }

        return view('baobab::admin.content.revisions', [
            'contentType' => $type,
            'slug' => $contentType,
            'label' => $this->label($type),
            'entryId' => $model->getKey(),
            'history' => $history,
            'workingDraft' => $this->workingDraftFor($model),
            'diff' => $diff,
            'fromId' => $fromId ?: null,
            'toId' => $toId ?: null,
            'canUpdate' => $this->actor()->can('update', $model),
        ]);
    }

    public function restoreRevision(string $contentType, int|string $entry, int $revision): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        $revisionModel = Revision::where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->findOrFail($revision);

        app(RestoreContentRevision::class)($type, $model, $revisionModel, $this->actor());

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.revision_restored')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    public function publishWorkingDraft(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();

        abort_unless($actor->can('publish', $model), 403);

        $draft = $this->workingDraftFor($model);
        abort_if($draft === null, 404);

        app(PublishWorkingDraftEntry::class)($type, $model, $draft, $actor);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_published')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    public function discardWorkingDraft(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        $draft = $this->workingDraftFor($model);

        if ($draft !== null) {
            app(DiscardWorkingDraftEntry::class)($type, $draft);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_discarded')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    /**
     * Soumet à validation la modification d'un contenu déjà publié (spec 09
     * §3 dernière puce, §5, M5 point 3) : même permission que le submit
     * natif (`update`, own/any vérifié par la policy), mais agit sur le
     * working draft, jamais sur la ligne `ct_*`.
     */
    public function submitWorkingDraft(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();

        abort_unless($actor->can('update', $model), 403);

        $draft = $this->workingDraftFor($model);
        abort_if($draft === null, 404);

        try {
            app(SubmitWorkingDraftForReview::class)($type, $draft);
        } catch (InvalidContentTransitionException $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_submitted')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    /**
     * Approuve un working draft en attente de validation (spec 09 §3
     * dernière puce, §5, M5 point 3) : toujours `publish_any`, jamais own —
     * cohérent avec la table de permissions de `transition()`.
     */
    public function approveWorkingDraft(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();
        $prefix = 'content.'.Str::snake($type->key);

        abort_unless($actor->can("{$prefix}.publish_any"), 403);

        $draft = $this->workingDraftFor($model);
        abort_if($draft === null, 404);

        try {
            app(ApproveWorkingDraftReview::class)($type, $model, $draft, $actor);
        } catch (InvalidContentTransitionException $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_review_approved')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    /**
     * Rejette un working draft en attente de validation (spec 09 §3
     * dernière puce, §5, M5 point 3) : commentaire obligatoire, le brouillon
     * redevient éditable — rien n'est perdu.
     */
    public function rejectWorkingDraft(Request $request, string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();
        $prefix = 'content.'.Str::snake($type->key);

        abort_unless($actor->can("{$prefix}.publish_any"), 403);

        $draft = $this->workingDraftFor($model);
        abort_if($draft === null, 404);

        $comment = $request->validate(['comment' => ['required', 'string', 'min:3']])['comment'];

        try {
            app(RejectWorkingDraftReview::class)($type, $draft, $comment);
        } catch (InvalidContentTransitionException $e) {
            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.working_draft_review_rejected')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    /**
     * Autosave périodique (spec 09 §3) — pas de validation stricte, le
     * contenu peut être incomplet en cours de frappe.
     */
    public function autosave(Request $request, string $contentType, int|string $entry): JsonResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();

        abort_unless($actor->can('update', $model), 403);

        app(AutosaveContentEntry::class)($type, $model, $request->except(['_token', '_method']), $actor);

        return response()->json(['saved' => true]);
    }

    /**
     * Battement de cœur du verrou d'édition (spec 09 §7) — appelé toutes les
     * `baobab.content.lock_heartbeat_seconds` tant que le formulaire est
     * ouvert. 200 dans les deux cas (information, pas une erreur) : le
     * client compare `locked` à son propre état pour détecter une prise de
     * main (aucune notification persistée, M5 point 4 n'existe pas encore).
     */
    public function heartbeat(string $contentType, int|string $entry): JsonResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        try {
            app(AcquireOrRefreshContentLock::class)($model, $this->actor());

            return response()->json(['locked' => false]);
        } catch (ContentLockedException $e) {
            return response()->json(['locked' => true, 'message' => $e->getMessage()]);
        }
    }

    public function releaseLock(string $contentType, int|string $entry): JsonResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $this->authorizeInstance('update', $model);

        app(ReleaseContentLock::class)($model, $this->actor());

        return response()->json(['released' => true]);
    }

    public function takeOverLock(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findEntry($type, $entry);
        $actor = $this->actor();
        $prefix = 'content.'.Str::snake($type->key);

        abort_unless($actor->can("{$prefix}.update_any"), 403);

        app(TakeOverContentLock::class)($model, $actor);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.lock_taken_over')]);

        return redirect()->route('admin.content.edit', ['contentType' => $contentType, 'entry' => $model->getKey()]);
    }

    public function restore(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findTrashedEntry($type, $entry);

        abort_unless($this->actor()->can('restore', $model), 403);

        app(RestoreContentEntryFromTrash::class)($type, $model);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.restored')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType, 'trashed' => 1]);
    }

    public function forceDestroy(string $contentType, int|string $entry): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $model = $this->findTrashedEntry($type, $entry);

        abort_unless($this->actor()->can('forceDelete', $model), 403);

        app(PurgeContentEntry::class)($type, $model);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.purged')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType, 'trashed' => 1]);
    }

    public function bulkRestore(Request $request, string $contentType): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $actor = $this->actor();

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        foreach ($ids as $id) {
            $model = $this->findTrashedEntry($type, $id);

            if ($actor->can('restore', $model)) {
                app(RestoreContentEntryFromTrash::class)($type, $model);
            }
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.restored')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType, 'trashed' => 1]);
    }

    public function bulkForceDestroy(Request $request, string $contentType): RedirectResponse
    {
        $type = $this->resolveContentType($contentType);
        $actor = $this->actor();

        /** @var list<int> $ids */
        $ids = (array) $request->input('ids', []);

        foreach ($ids as $id) {
            $model = $this->findTrashedEntry($type, $id);

            if ($actor->can('forceDelete', $model)) {
                app(PurgeContentEntry::class)($type, $model);
            }
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.content.purged')]);

        return redirect()->route('admin.content.index', ['contentType' => $contentType, 'trashed' => 1]);
    }

    private function resolveContentType(string $slug): ContentType
    {
        $tableName = 'ct_'.str_replace('-', '_', $slug);

        $type = ContentType::where('table_name', $tableName)->first();

        abort_if($type === null || $type->module_id === null, 404);

        return $type;
    }

    private function findEntry(ContentType $type, int|string $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        $model = $modelClass::query()->find($id);

        abort_if($model === null, 404);

        return $model;
    }

    private function findTrashedEntry(ContentType $type, int|string $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        $model = ContentTrash::onlyTrashed($modelClass)->find($id);

        abort_if($model === null, 404);

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ContentType $type, ?Model $entry = null): array
    {
        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            if (($field['type'] ?? null) === 'json' && $request->filled($field['key'])) {
                $decoded = json_decode((string) $request->input($field['key']), true);
                $request->merge([$field['key'] => is_array($decoded) ? $decoded : []]);
            }
        }

        $validated = $request->validate($this->entryRules->rules($type));

        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            if (($field['type'] ?? null) === 'boolean') {
                $validated[$field['key']] = $request->boolean($field['key']);
            }
        }

        if ($type->is_addressable && isset($validated['slug'])) {
            $validated['slug'] = $this->entryRules->uniqueSlug($type, (string) $validated['slug'], $entry);
        }

        return $validated;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formFields(ContentType $type): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($type->blueprint['fields'] ?? []);

        if ($type->is_addressable) {
            array_unshift($fields, ['key' => 'slug', 'type' => 'slug', 'required' => true]);
        }

        if ($type->unpublishAtColumnExists()) {
            $fields[] = ['key' => 'unpublish_at', 'type' => 'unpublish_at', 'required' => false];
        }

        // Les relations `belongsTo` sont des voisines de `fields[]` dans le
        // blueprint, jamais des membres : elles n'apparaissaient donc dans aucun
        // formulaire (n° 140). Elles rejoignent la liste ici, avant le filtre,
        // pour qu'un module puisse les traiter comme les autres champs.
        foreach (BelongsToRelations::from((array) $type->blueprint) as $relation) {
            $fields[] = [
                'key' => $relation['column'],
                'type' => 'relation',
                'label' => $relation['label'],
                'target' => $relation['target'],
                'required' => $relation['required'],
            ];
        }

        /** @var list<array<string, mixed>> $filtered */
        $filtered = Hook::filter('baobab.content.form.fields', $fields, $type);

        return $filtered;
    }

    private function label(ContentType $type): string
    {
        return $type->blueprint['label']['singular'] ?? $type->key;
    }

    private function pluralLabel(ContentType $type): string
    {
        return $type->blueprint['label']['plural'] ?? $type->key;
    }

    /**
     * Configuration du formulaire d'édition consommée par `contentEditForm()`
     * (heartbeat de verrou, autosave). `null` en création : il n'y a ni entrée
     * à verrouiller, ni brouillon à sauvegarder automatiquement.
     *
     * Calculée ici et non dans la vue : elle y vivait dans un bloc `@php`
     * (suivi n° 138), au motif que Blade ne compile pas un `@if` placé dans la
     * liste d'attributs d'un tag de composant. C'est exact, mais cela plaidait
     * pour sortir le calcul de la balise — pas pour le faire dans la vue.
     *
     * `csrfToken` est déclaré nullable parce qu'il l'est : `csrf_token()`
     * rend `?string` (null sans session). Cet écran est derrière la session
     * admin, donc le cas ne se produit pas — mais le type dit ce qui est,
     * plutôt que de promettre ce que l'appel ne garantit pas. Le rendre non
     * nullable supposerait de servir le jeton autrement (le champ `@csrf` du
     * formulaire est déjà là), ce qui touche au JavaScript d'autosave : hors
     * du périmètre d'une passe de conformité.
     *
     * @return array<string, bool|int|string|null>
     */
    private function editFormConfig(string $contentType, int|string $entryId, bool $readOnly): array
    {
        $params = ['contentType' => $contentType, 'entry' => $entryId];

        return [
            'heartbeatUrl' => route('admin.content.lock.heartbeat', $params),
            'releaseUrl' => route('admin.content.lock.release', $params),
            'autosaveUrl' => route('admin.content.autosave', $params),
            'heartbeatSeconds' => (int) config('baobab.content.lock_heartbeat_seconds', 30),
            'autosaveSeconds' => (int) config('baobab.content.autosave_seconds', 60),
            'readOnly' => $readOnly,
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Blocs HTML entiers pré-rendus, injectés par un module après la liste
     * de champs (ex. la metabox SEO) — distinct de `baobab.content.form.fields`
     * (champs simples, valeur lue via `getAttribute()`, validés par
     * `validationRules()`) : une section porte sa propre validation/
     * sauvegarde, câblée via `baobab.content.saved`, jamais connue de ce
     * contrôleur (spec 07 §1 : « le moteur de contenu ne le connaît pas »).
     *
     * @return list<string>
     */
    private function formSections(ContentType $type, ?Model $entry): array
    {
        /** @var list<string> $sections */
        $sections = Hook::filter('baobab.content.form.sections', [], $type, $entry);

        return $sections;
    }

    /**
     * Formulaires existants proposés au sélecteur d'embed d'un champ
     * `richtext` (spec 14 §4, M8 point 6 Pass C4) — « un formulaire se place
     * dans du contenu via un champ richtext ». Toujours calculé, y compris
     * pour un Content Type sans aucun champ richtext : `field.richtext`
     * n'affiche le sélecteur que si la liste est non vide (`@if ($forms !== [])`),
     * un coût nul à ignorer.
     *
     * @return array<string, string>
     */
    private function embeddableForms(): array
    {
        return Form::query()->orderBy('title')->pluck('title', 'slug')->all();
    }

    /**
     * Enrichit chaque descripteur de champ avec tout ce que la vue du
     * formulaire a besoin d'afficher (label, valeur courante, options,
     * script d'auto-remplissage du slug) — la vue ne fait plus aucun calcul,
     * seulement de la lecture.
     *
     * @return list<array<string, mixed>>
     */
    private function formFieldsForView(ContentType $type, ?Model $entry): array
    {
        $titleField = $type->blueprint['title_field'] ?? null;

        return array_map(function (array $field) use ($entry, $titleField): array {
            $name = $field['key'];
            $value = $entry?->getAttribute($name);

            if ($field['type'] === 'unpublish_at' && $value !== null) {
                $value = $value->format('Y-m-d\TH:i');
            }

            $choices = $field['options']['choices'] ?? [];
            $isTitleSource = $titleField !== null && $name === $titleField;

            return [
                ...$field,
                'label' => FieldDisplay::label($field),
                'value' => $value,
                'choices' => $choices,
                'choice_options' => array_combine($choices, $choices),
                'json_value' => $value === null ? null : json_encode($value, JSON_PRETTY_PRINT),
                'media' => in_array($field['type'], ['image', 'file'], true) && $value !== null
                    ? Media::find($value)
                    : null,
                'gallery_items' => $field['type'] === 'gallery'
                    ? $this->galleryItems($entry, $name)
                    : [],
                'relation_options' => $field['type'] === 'relation'
                    ? $this->relationOptions((string) ($field['target'] ?? ''))
                    : [],
                'html_type' => match ($field['type']) {
                    'integer', 'decimal' => 'number',
                    'date' => 'date',
                    'datetime', 'unpublish_at' => 'datetime-local',
                    'time' => 'time',
                    'email' => 'email',
                    'tel' => 'tel',
                    'url' => 'url',
                    default => 'text',
                },
                'auto_slug_handler' => $isTitleSource
                    ? 'if (!slugManuallyEdited) { const el = document.getElementById(\'slug\'); if (el) { el.value = baobabSlugify($event.target.value); } }'
                    : '',
            ];
        }, $this->formFields($type));
    }

    /**
     * Entrées proposées pour une relation, prêtes pour le composant. Le libellé
     * suit la règle déjà en vigueur ailleurs dans l'admin (corbeille, file de
     * validation) : `title_field` si la cible en déclare un, sinon son premier
     * champ, sinon l'identifiant.
     *
     * Une cible inconnue ne fait pas tomber l'écran d'édition : elle rend une
     * liste vide, et le blueprint est de toute façon refusé à la validation —
     * `RelationTargetResolver` lève sur une cible qui n'existe pas.
     *
     * @return array<int|string, string>
     */
    private function relationOptions(string $target): array
    {
        if ($target === '') {
            return [];
        }

        try {
            $resolved = app(RelationTargetResolver::class)->resolve($target);
        } catch (UnknownRelationTargetException) {
            return [];
        }

        $targetType = ContentType::where('key', $target)->first();

        $labelColumn = $targetType instanceof ContentType
            ? ($targetType->blueprint['title_field'] ?? $targetType->blueprint['fields'][0]['key'] ?? null)
            : 'name';

        return RelationOptions::for($resolved['class'], is_string($labelColumn) ? $labelColumn : null);
    }

    /**
     * Résout la sélection courante d'un champ `gallery` (M4 point 4b-ii) —
     * pas de colonne propre à lire sur `$entry`, la sélection ordonnée vit
     * dans `media_usages` (M4 point 3).
     *
     * @return list<Media>
     */
    private function galleryItems(?Model $entry, string $fieldKey): array
    {
        if ($entry === null) {
            return [];
        }

        $usages = MediaUsage::query()
            ->where('usable_type', $entry->getMorphClass())
            ->where('usable_id', $entry->getKey())
            ->where('field_key', $fieldKey)
            ->orderBy('order')
            ->with('media')
            ->get();

        $items = [];

        foreach ($usages as $usage) {
            if ($usage->media !== null) {
                $items[] = $usage->media;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listColumns(ContentType $type, string $slug, bool $trashed = false): array
    {
        $columns = [['key' => 'id', 'label' => 'ID', 'sortable' => true]];

        foreach (array_slice((array) ($type->blueprint['fields'] ?? []), 0, 3) as $field) {
            $columns[] = ['key' => $field['key'], 'label' => FieldDisplay::label($field)];
        }

        if (! $trashed) {
            $columns[] = ['key' => 'status', 'label' => __('baobab::admin.content.column_status')];
        }

        $actor = $this->actor();
        $canPurge = $actor->can('baobab.trash.purge');

        $columns[] = [
            'key' => 'actions',
            'label' => '',
            'raw' => true,
            'render' => fn (Model $row): string => view('baobab::admin.content.partials.row-actions', [
                'trashed' => $trashed,
                'editUrl' => route('admin.content.edit', ['contentType' => $slug, 'entry' => $row->getKey()]),
                'deleteUrl' => route('admin.content.destroy', ['contentType' => $slug, 'entry' => $row->getKey()]),
                'restoreUrl' => route('admin.content.restore', ['contentType' => $slug, 'entry' => $row->getKey()]),
                'purgeUrl' => route('admin.content.force-destroy', ['contentType' => $slug, 'entry' => $row->getKey()]),
                'canPurge' => $canPurge,
            ])->render(),
        ];

        return $columns;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearch(Builder $query, ContentType $type, string $term): void
    {
        if ($term === '') {
            return;
        }

        $textFields = collect((array) ($type->blueprint['fields'] ?? []))
            ->filter(fn (array $field): bool => in_array($field['type'], ['text', 'textarea', 'richtext'], true))
            ->pluck('key');

        if ($textFields->isEmpty()) {
            return;
        }

        $query->where(function (Builder $inner) use ($textFields, $term): void {
            foreach ($textFields as $key) {
                $inner->orWhere((string) $key, 'like', "%{$term}%");
            }
        });
    }

    private function authorizeClass(string $ability, ContentType $type): void
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        abort_unless($this->actor()->can($ability, $modelClass), 403);
    }

    private function authorizeInstance(string $ability, Model $model): void
    {
        abort_unless($this->actor()->can($ability, $model), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
