<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\ContentTypes\Actions\DeleteContentEntry;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly FieldRegistry $fields) {}

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

        return view('baobab::admin.content.index', [
            'contentType' => $type,
            'slug' => $contentType,
            'columns' => $this->listColumns($type),
            'rows' => $rows,
            'statuses' => $statuses,
            'canCreate' => $this->actor()->can('create', $modelClass),
            'canBulkDelete' => $this->actor()->can("{$permissionPrefix}.delete_any") || $this->actor()->can("{$permissionPrefix}.delete"),
        ]);
    }

    public function create(string $contentType): View
    {
        $type = $this->resolveContentType($contentType);
        $this->authorizeClass('create', $type);

        return view('baobab::admin.content.form', [
            'contentType' => $type,
            'slug' => $contentType,
            'fields' => $this->formFields($type),
            'entry' => null,
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

        return view('baobab::admin.content.form', [
            'contentType' => $type,
            'slug' => $contentType,
            'fields' => $this->formFields($type),
            'entry' => $model,
        ]);
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

        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            $fieldType = $this->fields->resolve($field['type']);
            $typeRules = $fieldType->rules($field['key'], $field['options'] ?? []);

            $rules[$field['key']] = array_merge(
                ($field['required'] ?? false) ? ['required'] : ['nullable'],
                $typeRules,
            );
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

        /** @var list<array<string, mixed>> $filtered */
        $filtered = Hook::filter('baobab.content.form.fields', $fields, $type);

        return $filtered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listColumns(ContentType $type): array
    {
        $columns = [['key' => 'id', 'label' => 'ID', 'sortable' => true]];

        foreach (array_slice((array) ($type->blueprint['fields'] ?? []), 0, 3) as $field) {
            $columns[] = ['key' => $field['key'], 'label' => $field['key']];
        }

        $columns[] = ['key' => 'status', 'label' => __('baobab::admin.content.column_status')];

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
