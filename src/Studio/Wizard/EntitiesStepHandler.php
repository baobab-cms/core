<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Str;

/**
 * Étape 2 — Modèles & tables (spec-modules §5.2 étape 2, schéma
 * `module-blueprint.schema.json#/properties/entities`).
 *
 * La structure est imbriquée sur deux niveaux (N entités × M champs ×
 * K relations) : elle voyage donc en **une seule chaîne JSON** plutôt qu'en
 * notation de tableau HTML — patron exact du constructeur de menus
 * (`admin/menus/edit.blade.php`, M6 point 4a), et forme naturelle ici puisque
 * la destination est littéralement le blueprint JSON du brouillon.
 *
 * Aucune validation sémantique n'est faite ici : `SaveStudioWizardStep` fait
 * repasser le blueprint reconstruit par `ModuleBlueprint::fromDraftJson()`,
 * qui cross-valide déjà types de champs, options et cibles de relation. Ce
 * handler ne fait que normaliser.
 */
final class EntitiesStepHandler implements StudioStepHandler
{
    /** Types de relation gérés par `StudioRelationDefinitionGenerator` (Pass A), vérifiés. */
    private const RELATION_TYPES = ['one_to_one', 'one_to_many', 'many_to_many', 'polymorphic'];

    private const ON_DELETE = ['restrict', 'cascade', 'set_null'];

    /** Types de champs dont `optionsRules()` exige `choices` — le formulaire doit les saisir. */
    private const TYPES_NEEDING_CHOICES = ['select', 'multiselect', 'radio'];

    public function __construct(private readonly FieldRegistry $fields) {}

    public function number(): int
    {
        return 2;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.models');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.entities';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'entities' => ['required', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $decoded = json_decode((string) ($validated['entities'] ?? ''), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        /** @var list<array<string, mixed>> $existing */
        $existing = $blueprint['entities'] ?? [];

        $entities = [];

        foreach ($decoded as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $entities[] = $this->normalizeEntity($entity, $existing);
        }

        $blueprint['entities'] = $entities;

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        return [
            'entities' => $blueprint['entities'] ?? [],
        ];
    }

    /**
     * Catalogues poussés à la vue. Tous dérivés de leur source de vérité —
     * `FieldRegistry` pour les types de champs (17 enregistrés au boot, un
     * module peut en ajouter), `RelationTargetResolver::coreModelKeys()` pour
     * les modèles Core — jamais de liste recopiée à la main.
     */
    public function viewData(): array
    {
        return [
            'fieldTypes' => array_keys($this->fields->all()),
            'typesNeedingChoices' => self::TYPES_NEEDING_CHOICES,
            'relationTypes' => self::RELATION_TYPES,
            'onDeleteOptions' => self::ON_DELETE,
            'contentTypeTargets' => ContentType::whereNotNull('module_id')->orderBy('key')->pluck('key')->all(),
            'coreTargets' => RelationTargetResolver::coreModelKeys(),
        ];
    }

    /**
     * La saisie de cette étape tient dans une seule chaîne JSON : sans ce
     * redécodage, un blueprint refusé par la cross-validation renverrait le
     * formulaire à l'état **stocké** et effacerait tout ce qui vient d'être
     * saisi. Aucune normalisation ici — on réaffiche l'entrée telle qu'elle a
     * été soumise, pour que l'utilisateur retrouve exactement son écran et
     * corrige le seul champ que le message d'erreur désigne.
     */
    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['entities'] ?? null;

        if (! is_string($submitted)) {
            return $values;
        }

        $decoded = json_decode($submitted, associative: true);

        // JSON malformé : c'est justement l'un des cas d'erreur possibles,
        // il n'y a rien à réafficher — repli sur le blueprint stocké.
        if (! is_array($decoded)) {
            return $values;
        }

        return ['entities' => $decoded];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array<string, mixed>>  $existing
     * @return array<string, mixed>
     */
    private function normalizeEntity(array $entity, array $existing): array
    {
        $key = trim((string) ($entity['key'] ?? ''));
        $table = trim((string) ($entity['table'] ?? ''));

        $normalized = [
            'key' => $key,
            'table' => $table !== '' ? $table : $this->deriveTable($key),
            'options' => [
                'timestamps' => (bool) ($entity['options']['timestamps'] ?? true),
                'soft_deletes' => (bool) ($entity['options']['soft_deletes'] ?? false),
                'uuid' => (bool) ($entity['options']['uuid'] ?? false),
            ],
            'fields' => $this->normalizeFields($entity['fields'] ?? []),
            'relations' => $this->normalizeRelations($entity['relations'] ?? []),
        ];

        // Les surfaces de routes appartiennent à l'étape 4 : un
        // ré-enregistrement de l'étape 2 ne doit jamais les effacer.
        $routes = $this->existingRoutes($key, $existing);

        if ($routes !== null) {
            $normalized['routes'] = $routes;
        }

        return $normalized;
    }

    /**
     * Table proposée par convention (pluriel snake_case de la clé), éditable
     * côté formulaire — même dérivation que celle annoncée par le schéma.
     */
    private function deriveTable(string $key): string
    {
        return $key === '' ? '' : Str::snake(Str::plural($key));
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @return array<string, bool>|null
     */
    private function existingRoutes(string $key, array $existing): ?array
    {
        foreach ($existing as $entity) {
            if (($entity['key'] ?? null) === $key && isset($entity['routes']) && is_array($entity['routes'])) {
                /** @var array<string, bool> $routes */
                $routes = $entity['routes'];

                return $routes;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $entry = [
                'key' => trim((string) ($field['key'] ?? '')),
                'type' => trim((string) ($field['type'] ?? '')),
                'required' => (bool) ($field['required'] ?? false),
                'unique' => (bool) ($field['unique'] ?? false),
                'indexed' => (bool) ($field['indexed'] ?? false),
            ];

            // Seul `choices` est exposé par le formulaire (obligatoire pour
            // select/multiselect/radio) ; les autres options de type restent
            // à leurs défauts, toutes `nullable`. Un bloc `options` vide n'est
            // pas écrit, pour ne pas polluer le blueprint.
            $choices = $this->normalizeChoices($field['options']['choices'] ?? null);

            if ($choices !== []) {
                $entry['options'] = ['choices' => $choices];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function normalizeChoices(mixed $choices): array
    {
        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $choice) {
            $value = trim((string) $choice);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeRelations(mixed $relations): array
    {
        if (! is_array($relations)) {
            return [];
        }

        $normalized = [];

        foreach ($relations as $relation) {
            if (! is_array($relation)) {
                continue;
            }

            $normalized[] = [
                'key' => trim((string) ($relation['key'] ?? '')),
                'type' => trim((string) ($relation['type'] ?? '')),
                'target' => trim((string) ($relation['target'] ?? '')),
                'on_delete' => trim((string) ($relation['on_delete'] ?? 'restrict')),
            ];
        }

        return $normalized;
    }
}
