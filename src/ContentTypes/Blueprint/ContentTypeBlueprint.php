<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Blueprint;

use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Représentation validée d'un blueprint de Content Type (spec 02 §1.2, §9).
 * `fields()` est validé contre le FieldRegistry (M3 point 2) : type inconnu
 * ou options invalides pour le type déclaré sont rejetés. `relations()` est
 * validé contre RelationTargetResolver (M3 point 3) : le type est déjà
 * contraint par le schéma JSON (enum fermé), seule la cible est vérifiée ici
 * (dépend de l'état de la base — hors de portée du schéma JSON).
 */
final readonly class ContentTypeBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(
        string $json,
        ?BlueprintValidator $validator = null,
        ?FieldRegistry $fieldRegistry = null,
        ?RelationTargetResolver $relationTargets = null,
    ): self {
        ($validator ?? new BlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        self::validateFields($data['fields'] ?? [], $fieldRegistry ?? app(FieldRegistry::class));
        self::validateRelations($data['relations'] ?? [], $relationTargets ?? app(RelationTargetResolver::class));
        self::validateTitleField($data);
        self::validateDesignatedFields($data);
        self::validateUrlPrefix($data);
        self::validateSeoSchema($data);

        return new self($data);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function validateFields(array $fields, FieldRegistry $registry): void
    {
        foreach ($fields as $field) {
            $type = $field['type'];

            if (! $registry->has($type)) {
                throw InvalidBlueprintException::forField(
                    "fields.{$field['key']}.type",
                    "Type de champ inconnu : « {$type} »."
                );
            }

            $fieldType = $registry->resolve($type);
            $optionsRules = $fieldType->optionsRules();

            if ($optionsRules === []) {
                continue;
            }

            $result = Validator::make($field['options'] ?? [], $optionsRules);

            if ($result->fails()) {
                throw InvalidBlueprintException::forField(
                    "fields.{$field['key']}.options",
                    (string) $result->errors()->first()
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     */
    private static function validateRelations(array $relations, RelationTargetResolver $resolver): void
    {
        foreach ($relations as $relation) {
            try {
                $resolver->resolve((string) $relation['target']);
            } catch (UnknownRelationTargetException $e) {
                throw InvalidBlueprintException::forField(
                    "relations.{$relation['key']}.target",
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validateTitleField(array $data): void
    {
        if (! ($data['is_addressable'] ?? false)) {
            return;
        }

        $titleField = $data['title_field'] ?? null;

        if ($titleField === null) {
            throw InvalidBlueprintException::forField(
                'title_field',
                'Un type de contenu adressable doit déclarer title_field (source du slug auto-généré, spec 02 §4.2).'
            );
        }

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($data['fields'] ?? []);
        $field = collect($fields)->firstWhere('key', $titleField);

        if ($field === null) {
            throw InvalidBlueprintException::forField(
                'title_field',
                "Le champ « {$titleField} » n'existe pas dans fields[]."
            );
        }

        if (! in_array($field['type'], ['text', 'textarea', 'richtext'], true)) {
            throw InvalidBlueprintException::forField(
                'title_field',
                "Le champ « {$titleField} » doit être de type text, textarea ou richtext pour servir de source au slug."
            );
        }
    }

    /**
     * `body_field` et `image_field` (spec 02 §3.1) — facultatives, mais
     * vérifiées dès qu'elles sont écrites, exactement comme `title_field`.
     * Une désignation qui nomme un champ inexistant est une faute de frappe
     * silencieuse : sans ce contrôle, `FieldDisplay` retomberait sur la
     * déduction et le type rendrait « presque bien », ce qui est le pire des
     * cas — l'auteur croit avoir désigné, et le produit devine.
     *
     * @param  array<string, mixed>  $data
     */
    private static function validateDesignatedFields(array $data): void
    {
        $designations = [
            'body_field' => ['text', 'textarea', 'richtext'],
            'image_field' => ['image'],
        ];

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($data['fields'] ?? []);

        foreach ($designations as $designation => $allowedTypes) {
            $key = $data[$designation] ?? null;

            if ($key === null) {
                continue;
            }

            $field = collect($fields)->firstWhere('key', $key);

            if ($field === null) {
                throw InvalidBlueprintException::forField(
                    $designation,
                    "Le champ « {$key} » n'existe pas dans fields[]."
                );
            }

            if (! in_array($field['type'], $allowedTypes, true)) {
                $expected = implode(', ', $allowedTypes);

                throw InvalidBlueprintException::forField(
                    $designation,
                    "Le champ « {$key} » doit être de type {$expected}."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validateUrlPrefix(array $data): void
    {
        if (! ($data['is_addressable'] ?? false)) {
            return;
        }

        $prefix = self::resolveUrlPrefix($data);

        /** @var list<string> $reserved */
        $reserved = config('baobab.rendering.reserved_prefixes', [config('baobab.admin.path', 'admin'), 'api']);

        if (in_array($prefix, $reserved, true)) {
            throw InvalidBlueprintException::forField(
                'url_prefix',
                "Le préfixe « {$prefix} » est réservé et ne peut pas être utilisé par un Content Type."
            );
        }

        $taken = ContentType::where('is_addressable', true)
            ->where('key', '!=', $data['key'])
            ->get()
            ->contains(fn (ContentType $other): bool => self::resolveUrlPrefix($other->blueprint) === $prefix);

        if ($taken) {
            throw InvalidBlueprintException::forField(
                'url_prefix',
                "Le préfixe « {$prefix} » est déjà utilisé par un autre Content Type adressable."
            );
        }
    }

    /**
     * Chaque jeton `{xxx}` trouvé dans `seo.schema.properties` (spec 07 §7)
     * doit désigner `title` (jeton spécial, `EntryTitleResolver`) ou un
     * `fields[].key` existant — même patron que `validateTitleField()`,
     * cross-vérification au niveau structurel plutôt qu'un échec silencieux
     * au rendu (`ComposeJsonLd` ne fait plus confiance à un mapping non
     * validé).
     *
     * @param  array<string, mixed>  $data
     */
    private static function validateSeoSchema(array $data): void
    {
        $properties = $data['seo']['schema']['properties'] ?? null;

        if ($properties === null) {
            return;
        }

        /** @var list<string> $fieldKeys */
        $fieldKeys = collect((array) ($data['fields'] ?? []))->pluck('key')->all();

        foreach (self::extractTokens($properties) as $token) {
            if ($token !== 'title' && ! in_array($token, $fieldKeys, true)) {
                throw InvalidBlueprintException::forField(
                    'seo.schema.properties',
                    "Le jeton « {{$token}} » ne correspond à aucun champ déclaré dans fields[] (ni « title »)."
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function extractTokens(mixed $value): array
    {
        if (is_array($value)) {
            $tokens = [];

            foreach ($value as $item) {
                array_push($tokens, ...self::extractTokens($item));
            }

            return $tokens;
        }

        if (! is_string($value) || preg_match('/^\{([a-z_][a-z0-9_]*)\}$/', $value, $matches) !== 1) {
            return [];
        }

        return [$matches[1]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveUrlPrefix(array $data): string
    {
        return $data['url_prefix'] ?? Str::kebab(Str::plural((string) $data['key']));
    }

    public function key(): string
    {
        return $this->data['key'];
    }

    public function labelSingular(): string
    {
        return $this->data['label']['singular'];
    }

    public function labelPlural(): string
    {
        return $this->data['label']['plural'];
    }

    public function isAddressable(): bool
    {
        return $this->data['is_addressable'] ?? false;
    }

    /**
     * Préfixe d'URL public (spec 07 §3, spec 03 §3) — repli kebab-pluriel de
     * la clé si non déclaré. Non significatif si `isAddressable()` est faux.
     */
    public function urlPrefix(): string
    {
        return self::resolveUrlPrefix($this->data);
    }

    public function titleField(): ?string
    {
        return $this->data['title_field'] ?? null;
    }

    /**
     * Active le workflow de validation à un niveau (spec 09 §5) : soumission,
     * approbation/rejet. Désactivé par défaut — un type simple ne passe que
     * par les transitions directes (publish/schedule/unpublish/archive).
     */
    public function workflowEnabled(): bool
    {
        return $this->data['workflow'] ?? false;
    }

    /**
     * Colonne de convention optionnelle `unpublish_at` (dépublication
     * programmée, spec 09 §4) — activée par défaut, désactivable par type.
     */
    public function unpublishAtEnabled(): bool
    {
        return $this->data['unpublish_at'] ?? true;
    }

    /**
     * Lecture publique sans authentification des entrées `published` de ce
     * type (spec 08 §2.3) — n'a de sens que si `isAddressable()` est vrai ;
     * activée par défaut (« désactivable par type »), la lecture des autres
     * statuts/types exige toujours un acteur autorisé.
     */
    public function publicApiReadEnabled(): bool
    {
        return $this->data['public_api_read'] ?? true;
    }

    /**
     * Quota de révisions du type (spec 09 §6, spec 02 §9 décision 4) — `null`
     * signifie "utiliser `config('baobab.content.revisions_limit')`", `0`
     * désactive les révisions sur ce type.
     */
    public function revisionsLimit(): ?int
    {
        return $this->data['revisions']['limit'] ?? null;
    }

    /**
     * Champs exclus du snapshot de révision (spec 09 §6 : `"revisions":
     * {"except": ["view_count"]}`).
     *
     * @return list<string>
     */
    public function revisionsExcept(): array
    {
        return $this->data['revisions']['except'] ?? [];
    }

    /**
     * Mapping schema.org optionnel (spec 07 §7), consommé par
     * `Baobab\Seo\Actions\ComposeJsonLd` — `null` si le blueprint n'en
     * déclare aucun.
     *
     * @return array{type: string, properties?: array<string, mixed>}|null
     */
    public function seoSchema(): ?array
    {
        return $this->data['seo']['schema'] ?? null;
    }

    public function blueprintVersion(): int
    {
        return $this->data['blueprint_version'] ?? 1;
    }

    /**
     * @return list<array{key: string, type: string}>
     */
    public function fields(): array
    {
        return $this->data['fields'] ?? [];
    }

    /**
     * Sous-ensemble de `fields()` marqué `exposed_in_api` (spec 02 §3, spec
     * 08 §2.1) — seuls ces champs apparaissent dans les Resources REST et
     * sont éligibles à `?filter[]`/`?sort=`. Vrai par défaut : un champ
     * existant reste exposé tant qu'il n'en est pas retiré explicitement.
     *
     * @return list<array{key: string, type: string}>
     */
    public function apiExposedFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            static fn (array $field): bool => (bool) Arr::get($field, 'exposed_in_api', true),
        ));
    }

    /**
     * Sous-ensemble de `fields()` marqué `searchable` (spec 11 §3.1) — **opt-in**,
     * contrairement à `exposed_in_api` (opt-out) : un champ n'entre dans
     * l'index de recherche que déclaré explicitement, la recherche n'étant
     * pas un besoin universel de chaque champ comme l'est l'exposition API.
     *
     * @return list<array{key: string, type: string}>
     */
    public function searchableFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            static fn (array $field): bool => (bool) Arr::get($field, 'searchable', false),
        ));
    }

    /**
     * Pondération déclarative d'un champ cherchable (spec 11 §3.1, §11
     * décision 1) — fait partie du contrat quel que soit le driver Scout,
     * mais son honorabilité en dépend : pleinement appliquée par Meilisearch,
     * approximative ou ignorée par le driver `database` par défaut.
     *
     * @param  array<string, mixed>  $field
     */
    public function fieldWeight(array $field): int
    {
        return (int) Arr::get($field, 'weight', 1);
    }

    /**
     * @return list<array{key: string, type: string}>
     */
    public function relations(): array
    {
        return $this->data['relations'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
