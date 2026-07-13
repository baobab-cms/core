<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\ContentTypes\Actions\ApproveContentEntry;
use Baobab\ContentTypes\Actions\ArchiveContentEntry;
use Baobab\ContentTypes\Actions\DeleteContentEntry;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\RejectContentEntry;
use Baobab\ContentTypes\Actions\RestoreArchivedContentEntry;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Actions\ScheduleContentEntry;
use Baobab\ContentTypes\Actions\SubmitContentEntry;
use Baobab\ContentTypes\Actions\UnpublishContentEntry;
use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        private readonly FieldRegistry $fields,
        private readonly ContentStateMachine $machine,
    ) {}

    public function index(Request $request, string $contentType): View
    {
        $type = $this->resolveContentType($contentType);
        $this->authorizeClass('viewAny', $type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        /** @var list<string> $statuses */
        $statuses = $modelClass::query()->distinct()->pluck('status')->filter()->values()->all();

        $query = $modelClass::query();

        $this->applySearch($query, $type, (string) $request->string('q'));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        /** @var LengthAwarePaginator<int, Model> $rows */
        $rows = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $permissionPrefix = 'content.'.Str::snake($type->key);
        $canBulkDelete = $this->actor()->can("{$permissionPrefix}.delete_any") || $this->actor()->can("{$permissionPrefix}.delete");

        return view('baobab::admin.content.index', [
            'contentType' => $type,
            'slug' => $contentType,
            'columns' => $this->listColumns($type, $contentType),
            'rows' => $rows,
            'statuses' => $statuses,
            'canCreate' => $this->actor()->can('create', $modelClass),
            'bulkActions' => $canBulkDelete ? [[
                'route' => route('admin.content.bulk-delete', ['contentType' => $contentType]),
                'label' => __('baobab::admin.content.bulk_delete_action'),
            ]] : [],
        ]);
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
            'formMethod' => 'POST',
            'formAction' => route('admin.content.store', ['contentType' => $contentType]),
            'fields' => $this->formFieldsForView($type, null),
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

        return view('baobab::admin.content.form', [
            'contentType' => $type,
            'slug' => $contentType,
            'label' => $this->label($type),
            'isEdit' => true,
            'formMethod' => 'PUT',
            'formAction' => route('admin.content.update', ['contentType' => $contentType, 'entry' => $model->getKey()]),
            'fields' => $this->formFieldsForView($type, $model),
            'entryId' => $model->getKey(),
            'currentStatus' => $status,
            'publishedAt' => $model->getAttribute('published_at'),
            'availableTransitions' => $this->machine->availableTransitions($type, $status),
            'canUpdate' => $actor->can('update', $model),
            'canPublish' => $actor->can('publish', $model),
            'canPublishAny' => $actor->can("{$prefix}.publish_any"),
        ]);
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

        $validated = $this->validated($request, $type, $model);

        app(SaveContentEntry::class)($type, $validated, $this->actor(), $model);

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

        $validated = $request->validate($this->validationRules($type));

        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            if (($field['type'] ?? null) === 'boolean') {
                $validated[$field['key']] = $request->boolean($field['key']);
            }
        }

        if ($type->is_addressable && isset($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($type, (string) $validated['slug'], $entry);
        }

        return $validated;
    }

    /**
     * Désambiguïse un slug en conflit en lui ajoutant un suffixe `-2`, `-3`,
     * etc. (convention WordPress) plutôt que de rejeter la soumission — la
     * colonne `slug` reste unique en base, ce n'est qu'un choix d'UX côté
     * admin. Les lignes passées en corbeille comptent toujours : la
     * contrainte d'unicité en base ne les exempte pas.
     */
    private function uniqueSlug(ContentType $type, string $desired, ?Model $entry): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();
        $entryId = $entry?->getKey();

        $slug = $desired;
        $suffix = 2;

        while (
            $modelClass::query()
                ->withoutGlobalScopes()
                ->where('slug', $slug)
                ->when($entryId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $entryId))
                ->exists()
        ) {
            $slug = "{$desired}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function validationRules(ContentType $type): array
    {
        $rules = [];

        if ($type->is_addressable) {
            $rules['slug'] = array_merge(
                ['required'],
                $this->fields->resolve('slug')->rules('slug', []),
            );
        }

        if ($type->unpublishAtColumnExists()) {
            $rules['unpublish_at'] = ['nullable', 'date', 'after:now'];
        }

        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            $fieldType = $this->fields->resolve($field['type']);
            $typeRules = $fieldType->rules($field['key'], $field['options'] ?? []);

            $rules[$field['key']] = array_merge(
                ($field['required'] ?? false) ? ['required'] : ['nullable'],
                $typeRules,
            );

            if ($field['type'] === 'gallery') {
                $rules["{$field['key']}.*"] = ['integer', 'exists:media,id'];
            }
        }

        return $rules;
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

        /** @var list<array<string, mixed>> $filtered */
        $filtered = Hook::filter('baobab.content.form.fields', $fields, $type);

        return $filtered;
    }

    private function label(ContentType $type): string
    {
        return $type->blueprint['label']['singular'] ?? $type->key;
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
                'label' => Str::headline($name),
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
                'html_type' => match ($field['type']) {
                    'integer', 'decimal' => 'number',
                    'date' => 'date',
                    'datetime', 'unpublish_at' => 'datetime-local',
                    'time' => 'time',
                    default => 'text',
                },
                'auto_slug_handler' => $isTitleSource
                    ? 'if (!slugManuallyEdited) { const el = document.getElementById(\'slug\'); if (el) { el.value = baobabSlugify($event.target.value); } }'
                    : '',
            ];
        }, $this->formFields($type));
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
    private function listColumns(ContentType $type, string $slug): array
    {
        $columns = [['key' => 'id', 'label' => 'ID', 'sortable' => true]];

        foreach (array_slice((array) ($type->blueprint['fields'] ?? []), 0, 3) as $field) {
            $columns[] = ['key' => $field['key'], 'label' => $field['key']];
        }

        $columns[] = ['key' => 'status', 'label' => __('baobab::admin.content.column_status')];

        $columns[] = [
            'key' => 'actions',
            'label' => '',
            'raw' => true,
            'render' => fn (Model $row): string => view('baobab::admin.content.partials.row-actions', [
                'editUrl' => route('admin.content.edit', ['contentType' => $slug, 'entry' => $row->getKey()]),
                'deleteUrl' => route('admin.content.destroy', ['contentType' => $slug, 'entry' => $row->getKey()]),
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
