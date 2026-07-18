<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Support;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Construction des règles de validation Laravel d'un Content Type depuis son
 * blueprint (spec 02 §3, spec 08 §1 : « Validation (FormRequests partagées)...
 * vivent dans un seul endroit ») — extrait de
 * `Baobab\Admin\Content\Http\Controllers\ContentController` pour être
 * réutilisé tel quel par `Baobab\Api\Http\Controllers\ContentController`
 * (REST, M7 point 1) sans dupliquer la construction des règles par type de
 * champ (`FieldRegistry::resolve()->rules()`).
 */
final class ContentEntryRules
{
    public function __construct(private readonly FieldRegistry $fields) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ContentType $type, bool $partial = false): array
    {
        $rules = [];

        if ($type->is_addressable) {
            $rules['slug'] = array_merge(
                $partial ? ['sometimes', 'required'] : ['required'],
                $this->fields->resolve('slug')->rules('slug', []),
            );
        }

        if ($type->unpublishAtColumnExists()) {
            $rules['unpublish_at'] = ['nullable', 'date', 'after:now'];
        }

        foreach ((array) ($type->blueprint['fields'] ?? []) as $field) {
            $fieldType = $this->fields->resolve($field['type']);
            $typeRules = $fieldType->rules($field['key'], $field['options'] ?? []);
            $required = $field['required'] ?? false;

            $rules[$field['key']] = array_merge(
                $required ? ($partial ? ['sometimes', 'required'] : ['required']) : ['nullable'],
                $typeRules,
            );

            if ($field['type'] === 'gallery') {
                $rules["{$field['key']}.*"] = ['integer', 'exists:media,id'];
            }
        }

        return $rules;
    }

    /**
     * Désambiguïse un slug en conflit en lui ajoutant un suffixe `-2`, `-3`,
     * etc. (convention WordPress) plutôt que de rejeter la soumission — la
     * colonne `slug` reste unique en base, ce n'est qu'un choix d'UX. Les
     * lignes passées en corbeille comptent toujours : la contrainte
     * d'unicité en base ne les exempte pas.
     */
    public function uniqueSlug(ContentType $type, string $desired, ?Model $entry): string
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
}
