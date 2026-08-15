<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator\Profiles;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\StudioRelationDefinitionGenerator;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
use Illuminate\Support\Str;

/**
 * Un module, **plus les conventions de contenu** (spec 02 §4.2, §9 ; spec 08
 * §3.2 pour GraphQL) — l'héritage dit littéralement ce qu'énonce la
 * spec-modules §6 : « le Content Type builder est un cas particulier simplifié
 * du Studio ».
 *
 * Ce que ce profil ajoute au module ordinaire :
 * socle de colonnes éditoriales (`status`, `published_at`, `unpublish_at`,
 * `slug`, `author_id`), timestamps et soft deletes non négociables, préfixe
 * `ct_` sur les tables pivots, policy own/any adossée à `author_id`,
 * permissions et entrée de menu dérivées du type, fragment GraphQL et son
 * résolveur, trait Scout quand au moins un champ est `searchable`.
 *
 * Ce qu'il **retire** : toutes les surfaces générées. Un Content Type n'a ni
 * contrôleur ni route à lui — son admin est rendu par le `ContentController`
 * générique du Core et son API par les routes génériques, ce qui est
 * précisément la raison pour laquelle `ContentTypeModuleGenerator` n'avait pas
 * pu réutiliser le générateur du Studio à l'époque de la Pass A1.
 *
 * Le profil porte le `ContentType` lui-même parce que l'enveloppe du blueprint
 * de contenu (`is_addressable`, `unpublish_at`, `searchable`, `exposed_in_api`)
 * n'a pas d'équivalent dans le blueprint de module — c'est la moitié
 * volontairement non fusionnée du point 2 (suivi n° 157).
 */
final class ContentTypeProfile extends ModuleProfile
{
    public function __construct(
        private readonly ContentType $contentType,
        private readonly FieldRegistry $fields,
        private readonly StudioRelationDefinitionGenerator $relationDefinitions,
        private readonly StudioRelationTargetResolver $relationTargets,
    ) {}

    public function manifestType(): string
    {
        return 'content-type';
    }

    public function moduleDir(string $name): string
    {
        return $this->contentType->moduleDir();
    }

    public function rootNamespace(string $name, ModuleBlueprint $blueprint): string
    {
        return "Modules\\{$this->contentType->key}";
    }

    public function providerClass(string $slug, ModuleBlueprint $blueprint): string
    {
        return "{$this->contentType->key}ServiceProvider";
    }

    public function permissionPrefix(string $slug, string $entityKey): string
    {
        return 'content.'.Str::snake($entityKey);
    }

    public function pivotPrefix(): string
    {
        return 'ct_';
    }

    /**
     * **Non** — un Content Type garde le nommage horodaté et aléatoire, donc le
     * défaut du n° 120 (ordre tiré au sort entre deux migrations de la même
     * seconde, archive non réinstallable) que le n° 151 a corrigé pour les
     * modules du Studio.
     *
     * Ce n'est pas une propriété du moteur mais une dette explicite, mesurée en
     * livrant cette passe : le nommage déterministe rend le chemin d'une
     * migration stable, or Laravel met en cache les migrations résolues par
     * chemin pour tout le processus. La suite de tests construit 78 fois un
     * type nommé `Car` avec des blueprints différents, et le modèle Eloquent
     * généré ne peut être déclaré qu'une fois par processus : l'étendre aux
     * Content Types demande d'abord la même convergence de fixtures que le
     * n° 120 a imposée aux modules (« un nom de module, un schéma »,
     * `tests/Pest.php`). Détail, coût et patron de correction : suivi n° 160.
     */
    public function ranksMigrations(): bool
    {
        return false;
    }

    /**
     * Le socle commun de la spec 02 §4.2, dans l'ordre où il était écrit
     * jusqu'ici : `id`, statut éditorial, dates de publication, slug des types
     * adressables, auteur.
     */
    public function leadingColumns(array $entity): string
    {
        return implode("\n", array_filter([
            '            $table->id();',
            "            \$table->string('status')->default('draft');",
            "            \$table->timestamp('published_at')->nullable();",
            $this->contentType->unpublishAtEnabled()
                ? "            \$table->timestamp('unpublish_at')->nullable();"
                : null,
            $this->contentType->is_addressable
                ? "            \$table->string('slug')->unique();"
                : null,
            "            \$table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();",
        ]));
    }

    /**
     * Non négociables, contrairement au module ordinaire : les timestamps et
     * les soft deletes branchent le type sur la corbeille, les révisions et le
     * cycle éditorial (spec 02 §4.2).
     */
    public function trailingColumns(array $entity): string
    {
        return implode("\n", [
            '            $table->timestamps();',
            '            $table->softDeletes();',
        ]);
    }

    public function modelTraits(array $entity): array
    {
        $searchable = $this->contentType->searchableFields() !== [];

        return [
            'imports' => implode("\n", array_filter([
                'use Illuminate\\Database\\Eloquent\\SoftDeletes;',
                $searchable ? 'use Laravel\\Scout\\Searchable;' : null,
            ])),
            'use' => $searchable ? '    use SoftDeletes, Searchable;' : '    use SoftDeletes;',
        ];
    }

    public function leadingCasts(array $entity): string
    {
        return implode("\n", array_filter([
            "            'published_at' => 'datetime',",
            $this->contentType->unpublishAtEnabled()
                ? "            'unpublish_at' => 'datetime',"
                : null,
        ]));
    }

    public function leadingFillable(array $entity): array
    {
        return array_values(array_filter([
            'status',
            'published_at',
            'author_id',
            $this->contentType->unpublishAtEnabled() ? 'unpublish_at' : null,
            $this->contentType->is_addressable ? 'slug' : null,
        ]));
    }

    /**
     * Génère `toSearchableArray()` (contrat `Laravel\Scout\Searchable`, spec 11
     * §3.1) — jamais écrit à la main par le développeur. Seuls les champs
     * `searchable` du blueprint y figurent ; `id`/`status` structurels toujours
     * inclus (utiles à toute source de recherche pour résoudre l'entrée réelle
     * et son état éditorial). Le document passe par le filtre
     * `baobab.search.indexing` (spec 11 §4.4) : un module peut modifier le
     * document indexé d'un enregistrement — le modèle généré dépend déjà du
     * Core (trait Scout configuré par lui), la façade `Hook` n'ajoute aucun
     * couplage nouveau.
     */
    public function modelMethods(array $entity): string
    {
        $searchableFields = $this->contentType->searchableFields();

        if ($searchableFields === []) {
            return '';
        }

        $lines = collect($searchableFields)
            ->map(fn (array $field): string => "            '{$field['key']}' => \$this->{$field['key']},")
            ->implode("\n");

        return "\n    /**\n     * @return array<string, mixed>\n     */\n    public function toSearchableArray(): array\n    {\n        return \\Baobab\\Facades\\Hook::filter('baobab.search.indexing', [\n            'id' => \$this->id,\n            'status' => \$this->status,\n{$lines}\n        ], \$this);\n    }\n";
    }

    public function policy(array $entity, string $namespace, string $permissionPrefix, ModuleBlueprint $blueprint): string
    {
        return (new StubRenderer)->render(StubRenderer::stubPath('policy'), [
            'namespace' => $namespace,
            'key' => (string) $entity['key'],
            'var' => Str::camel((string) $entity['key']),
            'permission_prefix' => $permissionPrefix,
        ]);
    }

    public function providerFiles(string $namespace, string $slug, ModuleBlueprint $blueprint): array
    {
        $key = $this->contentType->key;

        return ["src/Providers/{$key}ServiceProvider.php" => (new StubRenderer)->render(StubRenderer::stubPath('provider'), [
            'namespace' => $namespace,
            'key' => $key,
        ])];
    }

    /**
     * Fragment `.graphql` du type et son résolveur (spec 08 §3.2) — dérivés du
     * blueprint, seuls les champs `exposed_in_api` y figurent (même filtre que
     * le REST, `ContentType::apiExposedFields()`), parité stricte avec
     * `Baobab\Api\Http\Resources\ContentEntryResource`.
     */
    public function extraEntityFiles(array $entity, string $namespace): array
    {
        $key = (string) $entity['key'];
        $resolverClass = str_replace('\\', '\\\\', "{$namespace}\\GraphQL\\{$key}Resolver");
        $label = $this->contentType->blueprint['label']['singular'] ?? $key;

        return [
            "graphql/{$key}.graphql" => (new StubRenderer)->render(StubRenderer::stubPath('graphql-type'), [
                'label' => (string) $label,
                'key' => $key,
                'fields' => $this->graphqlTypeFields(),
                'relations' => $this->graphqlRelationFields($entity),
                'filter_fields' => $this->graphqlFilterFields(),
                'sort_values' => $this->graphqlSortValues(),
                'query_plural' => Str::camel(Str::plural($key)),
                'query_singular' => Str::camel($key),
                'resolver_class' => $resolverClass,
            ]),
            "src/GraphQL/{$key}Resolver.php" => (new StubRenderer)->render(StubRenderer::stubPath('graphql-resolver'), [
                'namespace' => $namespace,
                'key' => $key,
                'slug' => Str::kebab(Str::plural($key)),
            ]),
        ];
    }

    /**
     * Un Content Type n'écrit ni contrôleur, ni route, ni widget : son admin
     * passe par le `ContentController` générique du Core et son API par les
     * routes génériques (spec 02 §6, §7).
     */
    public function generatesSurfaces(): bool
    {
        return false;
    }

    public function permissions(ModuleBlueprint $blueprint, string $slug): array
    {
        $prefix = $this->permissionPrefix($slug, $this->contentType->key);
        $label = $this->labels();

        return [
            ['key' => "{$prefix}.view", 'label' => "Voir : {$label['plural']}"],
            ['key' => "{$prefix}.create", 'label' => "Créer : {$label['singular']}"],
            ['key' => "{$prefix}.update", 'label' => "Modifier (les siens) : {$label['singular']}"],
            ['key' => "{$prefix}.update_any", 'label' => "Modifier (tous) : {$label['plural']}"],
            ['key' => "{$prefix}.delete", 'label' => "Supprimer (les siens) : {$label['singular']}"],
            ['key' => "{$prefix}.delete_any", 'label' => "Supprimer (tous) : {$label['plural']}"],
            ['key' => "{$prefix}.publish", 'label' => "Publier (les siens) : {$label['singular']}"],
            ['key' => "{$prefix}.publish_any", 'label' => "Publier (tous) : {$label['plural']}"],
        ];
    }

    /**
     * Toujours une entrée, jamais `null` : un Content Type sans entrée de menu
     * serait invisible de l'admin alors que c'est son unique surface.
     *
     * @return array{admin: list<array<string, mixed>>}
     */
    public function menus(ModuleBlueprint $blueprint): array
    {
        $key = $this->contentType->key;
        $label = $this->labels();

        return ['admin' => [[
            'label' => $label['plural'],
            'route' => 'admin.content.index',
            'route_params' => ['contentType' => Str::kebab(Str::plural($key))],
            'permission' => $this->permissionPrefix('', $key).'.view',
        ]]];
    }

    /**
     * @return array{singular: string, plural: string}
     */
    private function labels(): array
    {
        $key = $this->contentType->key;

        /** @var array{singular?: string, plural?: string} $label */
        $label = $this->contentType->blueprint['label'] ?? [];

        return [
            'singular' => (string) ($label['singular'] ?? $key),
            'plural' => (string) ($label['plural'] ?? $key),
        ];
    }

    private function graphqlTypeFields(): string
    {
        $lines = $this->contentType->is_addressable ? ['  slug: String!'] : [];

        foreach ($this->contentType->apiExposedFields() as $field) {
            $graphqlType = $this->fields->resolve($field['type'])->graphqlType($field['options'] ?? []);
            $lines[] = "  {$field['key']}: {$graphqlType}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function graphqlRelationFields(array $entity): string
    {
        $siblings = [['key' => (string) $entity['key'], 'table' => (string) $entity['table']]];

        return collect((array) ($entity['relations'] ?? []))
            ->map(fn (array $relation): string => $this->relationDefinitions->graphqlField(
                $relation,
                $this->relationTargets->resolve((string) $relation['target'], $siblings),
            ))
            ->implode("\n");
    }

    private function graphqlFilterFields(): string
    {
        $lines = $this->contentType->is_addressable ? ['  slug: StringFilterInput'] : [];

        foreach ($this->contentType->apiExposedFields() as $field) {
            $graphqlType = $this->fields->resolve($field['type'])->graphqlType($field['options'] ?? []);
            $filterInput = self::filterInputFor($graphqlType);

            if ($filterInput !== null) {
                $lines[] = "  {$field['key']}: {$filterInput}";
            }
        }

        return implode("\n", $lines);
    }

    private function graphqlSortValues(): string
    {
        $lines = $this->contentType->is_addressable ? ['  SLUG @enum(value: "slug")'] : [];

        foreach ($this->contentType->apiExposedFields() as $field) {
            $graphqlType = $this->fields->resolve($field['type'])->graphqlType($field['options'] ?? []);

            if (self::filterInputFor($graphqlType) === null) {
                continue;
            }

            $enumValue = Str::upper(Str::snake((string) $field['key']));
            $lines[] = "  {$enumValue} @enum(value: \"{$field['key']}\")";
        }

        return implode("\n", $lines);
    }

    /**
     * Types filtrables/triables en GraphQL — uniquement les scalaires
     * comparables par un opérateur simple (`ContentQueryBuilder`) ;
     * `Media`/`[Media]`/`[String]`/`JSON` restent exposés comme champs mais
     * sortent du vocabulaire de filtre/tri (un opérateur générique dessus n'a
     * aucun sens SQL, contrairement aux scalaires).
     */
    private static function filterInputFor(string $graphqlType): ?string
    {
        return match ($graphqlType) {
            'String' => 'StringFilterInput',
            'Int' => 'IntFilterInput',
            'Float' => 'FloatFilterInput',
            'Boolean' => 'BooleanFilterInput',
            'Date', 'DateTime', 'Time' => 'DateTimeFilterInput',
            default => null,
        };
    }
}
