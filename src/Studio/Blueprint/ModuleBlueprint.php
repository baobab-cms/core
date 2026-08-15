<?php

declare(strict_types=1);

namespace Baobab\Studio\Blueprint;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Exceptions\UnknownStudioRelationTargetException;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
use Baobab\Studio\Support\BlueprintIdentifiers;
use Illuminate\Support\Facades\Validator;

/**
 * Représentation validée d'un blueprint de module Wizard Studio
 * (spec-modules §5.2, §5.4). Contrairement à `ContentTypeBlueprint` (une
 * seule entité), modélise **N entités**, chacune avec ses propres
 * `fields[]`/`relations[]` — sous-forme volontairement identique à
 * `ContentTypeBlueprint`, ce qui a permis à la Pass A du M8 point 2
 * (spec-modules §6) de faire passer les Content Types par le moteur de
 * génération commun sans toucher au catalogue de champs ni au générateur de
 * relations. L'unification des deux **formats**, elle, reste la moitié
 * différée du point (suivi n° 157) : c'est pourquoi un `ContentType` est
 * *projeté* ici par `ContentTypeModuleGenerator` plutôt que d'y être stocké.
 *
 * Trois modes de construction : `fromJson()` (strict, schéma complet — utilisé
 * à la génération), `fromDraftJson()` (permissif — un brouillon en cours de
 * saisie aux étapes 1-9 du wizard n'a pas à satisfaire le schéma complet,
 * seules les entités déjà déclarées sont cross-vérifiées) et `fromValidated()`
 * (aucune vérification — réservé à la projection d'un blueprint déjà validé
 * sous son propre jeu de règles, voir son docblock).
 */
final readonly class ModuleBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(
        string $json,
        ?ModuleBlueprintValidator $validator = null,
        ?FieldRegistry $fieldRegistry = null,
        ?StudioRelationTargetResolver $relationTargets = null,
    ): self {
        ($validator ?? new ModuleBlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        $registry = $fieldRegistry ?? app(FieldRegistry::class);

        self::validateEntities($data['entities'] ?? [], $registry, $relationTargets ?? app(StudioRelationTargetResolver::class));
        self::validateWidgets($data['widgets'] ?? [], $registry);
        self::validatePermissions($data['permissions'] ?? [], $data['entities'] ?? []);
        self::validateHooks($data['hooks'] ?? []);
        self::validateMenus($data['menus'] ?? []);
        self::validateSurfacesAgainstPermissions($data['permissions'] ?? [], $data['entities'] ?? []);

        return new self($data);
    }

    /**
     * Construction permissive pour un brouillon (`ModuleBlueprintDraft`) en
     * cours de saisie : le JSON doit être un objet valide, mais aucune
     * section n'est requise — seules les entités déjà présentes sont
     * cross-vérifiées (types de champs, cibles de relation).
     */
    public static function fromDraftJson(
        string $json,
        ?FieldRegistry $fieldRegistry = null,
        ?StudioRelationTargetResolver $relationTargets = null,
    ): self {
        $data = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $registry = $fieldRegistry ?? app(FieldRegistry::class);

        self::validateEntities($data['entities'] ?? [], $registry, $relationTargets ?? app(StudioRelationTargetResolver::class));
        self::validateWidgets($data['widgets'] ?? [], $registry);
        self::validatePermissions($data['permissions'] ?? [], $data['entities'] ?? []);
        self::validateHooks($data['hooks'] ?? []);
        self::validateMenus($data['menus'] ?? []);

        return new self($data);
    }

    /**
     * Blueprint **déjà validé ailleurs**, monté sans repasser par les
     * vérifications croisées (M8 point 2, Pass A — suivi n° 157).
     *
     * Unique appelant prévu : la projection d'un `ContentType` en blueprint de
     * module, faite par `ContentTypeModuleGenerator` pour alimenter le moteur
     * commun. Le blueprint de contenu a déjà été validé par
     * `ContentTypeBlueprint::fromJson()` au moment où il a été créé ou évolué,
     * contre le catalogue de champs et le résolveur de cibles qui le
     * concernent. Le repasser ici sous un **second** jeu de règles n'ajouterait
     * aucune sûreté : il ouvrirait la possibilité que les deux ne soient pas
     * d'accord, et un type parfaitement valide deviendrait ingénérable — y
     * compris pendant `CompileGraphqlSchema`, qui régénère le fragment de
     * *chaque* type éligible et tomberait alors en entier.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self($data);
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     */
    private static function validateEntities(array $entities, FieldRegistry $fieldRegistry, StudioRelationTargetResolver $relationTargets): void
    {
        $keys = [];
        $tables = [];

        foreach ($entities as $entity) {
            $key = $entity['key'] ?? null;

            if ($key !== null && in_array($key, $keys, true)) {
                throw InvalidModuleBlueprintException::forField(
                    'entities',
                    "La clé d'entité « {$key} » est déclarée plus d'une fois dans ce blueprint."
                );
            }

            if ($key !== null) {
                $keys[] = $key;
            }

            // Deux entités qui visent la même table produiraient deux
            // migrations de création pour un seul `CREATE TABLE` possible : la
            // seconde échouerait à l'installation sur un « table already
            // exists » brut. Refusé ici, où la faute se nomme, plutôt que subi
            // en SQL (suivi n° 120, même principe que le refus d'un cycle de
            // dépendances).
            $table = $entity['table'] ?? null;

            if ($table !== null && in_array($table, $tables, true)) {
                throw InvalidModuleBlueprintException::forField(
                    'entities',
                    "La table « {$table} » est déclarée par plus d'une entité de ce blueprint. "
                    .'Une table n\'a qu\'une migration de création : donnez à chaque entité sa propre table.'
                );
            }

            if ($table !== null) {
                $tables[] = $table;
            }
        }

        /** @var list<array{key: string, table: string}> $siblings */
        $siblings = array_map(
            static fn (array $entity): array => ['key' => $entity['key'], 'table' => $entity['table']],
            array_values(array_filter($entities, static fn (array $entity): bool => isset($entity['key'], $entity['table']))),
        );

        foreach ($entities as $entity) {
            self::validateIdentifiers($entity);
            self::validateFields($entity, $fieldRegistry);
            self::validateRelations($entity, $siblings, $relationTargets);
        }
    }

    /**
     * Motifs des identifiants d'une entité, vérifiés **dès le brouillon**
     * (donc à l'étape où ils sont saisis) et non plus seulement au schéma
     * complet de la génération : un libellé humain tapé dans un champ « clé »
     * ne doit pas traverser huit étapes avant d'être refusé. Cf.
     * `BlueprintIdentifiers`.
     *
     * @param  array<string, mixed>  $entity
     */
    private static function validateIdentifiers(array $entity): void
    {
        $entityKey = $entity['key'] ?? '?';

        BlueprintIdentifiers::check(
            BlueprintIdentifiers::CLASS_NAME,
            $entity['key'] ?? null,
            "entities.{$entityKey}.key",
            'un nom de modèle s\'écrit en PascalCase, au singulier (Car, BlogPost)'
        );

        BlueprintIdentifiers::check(
            BlueprintIdentifiers::SNAKE,
            $entity['table'] ?? null,
            "entities.{$entityKey}.table",
            'un nom de table s\'écrit en minuscules, avec des underscores (cars, blog_posts)'
        );

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            BlueprintIdentifiers::check(
                BlueprintIdentifiers::SNAKE,
                $field['key'] ?? null,
                "entities.{$entityKey}.fields.".($field['key'] ?? '?'),
                'une clé de champ devient un nom de colonne : minuscules et underscores uniquement (title, published_at)'
            );
        }

        foreach ((array) ($entity['relations'] ?? []) as $relation) {
            BlueprintIdentifiers::check(
                BlueprintIdentifiers::SNAKE,
                $relation['key'] ?? null,
                "entities.{$entityKey}.relations.".($relation['key'] ?? '?'),
                'une clé de relation devient un nom de méthode : minuscules et underscores uniquement (brand, blog_posts)'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private static function validateFields(array $entity, FieldRegistry $registry): void
    {
        $entityKey = $entity['key'] ?? '?';

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            $type = $field['type'];

            if (! $registry->has($type)) {
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.fields.{$field['key']}.type",
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
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.fields.{$field['key']}.options",
                    (string) $result->errors()->first()
                );
            }
        }
    }

    /**
     * Réutilise `validateFields()` telle quelle : `settings_fields` d'un
     * widget a la même forme que `fields` d'une entité, donc le même
     * contrôle de type/options s'applique sans duplication de logique.
     *
     * @param  list<array<string, mixed>>  $widgets
     */
    private static function validateWidgets(array $widgets, FieldRegistry $registry): void
    {
        foreach ($widgets as $widget) {
            $widgetKey = $widget['key'] ?? '?';

            BlueprintIdentifiers::check(
                BlueprintIdentifiers::WIDGET_KEY,
                $widget['key'] ?? null,
                "widgets.{$widgetKey}.key",
                'une clé de widget se préfixe par le module et s\'écrit en minuscules (fleet.latest-cars)'
            );

            BlueprintIdentifiers::check(
                BlueprintIdentifiers::CLASS_NAME,
                $widget['class_name'] ?? null,
                "widgets.{$widgetKey}.class_name",
                'un nom de classe s\'écrit en PascalCase, sans namespace (LatestCars)'
            );

            self::validateFields([
                'key' => $widgetKey,
                'fields' => $widget['settings_fields'] ?? [],
            ], $registry);

            foreach ((array) ($widget['settings_fields'] ?? []) as $field) {
                BlueprintIdentifiers::check(
                    BlueprintIdentifiers::SNAKE,
                    $field['key'] ?? null,
                    "widgets.{$widgetKey}.settings_fields.".($field['key'] ?? '?'),
                    'une clé de réglage s\'écrit en minuscules, avec des underscores (limit, display_mode)'
                );
            }
        }
    }

    /**
     * Une entrée de menu **sans route et sans sous-entrée ne s'affiche jamais** :
     * `SidebarBuilder::toSidebarItem()` la filtre explicitement (une entrée de
     * simple regroupement qui ne regroupe rien n'a pas de sens). Le wizard
     * proposait pourtant « Aucune route » comme choix isolé, et générait donc
     * un module dont l'entrée de menu n'apparaissait nulle part — défaut réel
     * signalé en vérification navigateur de la Pass B5.
     *
     * Refusé **dès le brouillon**, contrairement au contrôle
     * `auto_crud` × surface (n° 103) qui n'a lieu qu'à la génération : là-bas,
     * les deux réglages vivaient sur deux étapes différentes et bloquer tôt
     * aurait enfermé l'utilisateur ; ici, la route et les sous-entrées se
     * saisissent sur le même écran, donc l'erreur se montre là où on l'a
     * commise (leçon du n° 107).
     *
     * @param  array<string, mixed>  $menus
     */
    private static function validateMenus(array $menus): void
    {
        self::validateMenuItems($menus['admin'] ?? []);
    }

    /**
     * @param  array<mixed, mixed>  $items
     */
    private static function validateMenuItems(array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = (string) ($item['label'] ?? '?');
            $children = (array) ($item['children'] ?? []);

            if (($item['route'] ?? '') === '' && $children === []) {
                throw InvalidModuleBlueprintException::forField(
                    "menus.admin.{$label}",
                    "L'entrée de menu « {$label} » n'a ni route ni sous-entrée : elle ne s'afficherait jamais dans la barre latérale. Donnez-lui une route, ou au moins une sous-entrée."
                );
            }

            self::validateMenuItems($children);
        }
    }

    /**
     * Motifs des noms de hooks et des classes d'écouteurs (étape 8).
     *
     * @param  array<string, mixed>  $hooks
     */
    private static function validateHooks(array $hooks): void
    {
        foreach ($hooks['emits'] ?? [] as $hook) {
            BlueprintIdentifiers::check(
                BlueprintIdentifiers::HOOK_NAME,
                $hook,
                'hooks.emits.'.(is_string($hook) ? $hook : '?'),
                'un nom de hook s\'écrit en segments pointés, en minuscules (fleet.car.serviced)'
            );
        }

        foreach ($hooks['listens'] ?? [] as $hook => $className) {
            BlueprintIdentifiers::check(
                BlueprintIdentifiers::HOOK_NAME,
                $hook,
                "hooks.listens.{$hook}",
                'un nom de hook s\'écrit en segments pointés, en minuscules (baobab.content.saved)'
            );

            BlueprintIdentifiers::check(
                BlueprintIdentifiers::CLASS_NAME,
                $className,
                "hooks.listens.{$hook}",
                'un nom de classe d\'écouteur s\'écrit en PascalCase, sans namespace (SyncCarIndex)'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private static function validateRelations(array $entity, array $siblings, StudioRelationTargetResolver $resolver): void
    {
        $entityKey = $entity['key'] ?? '?';

        foreach ((array) ($entity['relations'] ?? []) as $relation) {
            try {
                $resolver->resolve((string) $relation['target'], $siblings);
            } catch (UnknownStudioRelationTargetException $e) {
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.relations.{$relation['key']}.target",
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Une permission personnalisée est **portée par une entité** : son préfixe
     * est dérivé de la clé d'entité (`BlueprintPermissions::prefix()`), donc
     * une entité disparue laisserait une permission orpheline dans le
     * manifeste et une méthode de policy sur un modèle inexistant. Le schéma
     * JSON ne peut pas vérifier ce lien (il ne voit qu'un motif de chaîne) —
     * c'est le rôle de cette cross-validation, au même titre que les cibles de
     * relation.
     *
     * @param  array<string, mixed>  $permissions
     * @param  list<array<string, mixed>>  $entities
     */
    private static function validatePermissions(array $permissions, array $entities): void
    {
        $entityKeys = array_map(static fn (array $entity): mixed => $entity['key'] ?? null, $entities);

        $seen = [];

        foreach ($permissions['custom'] ?? [] as $permission) {
            $entity = (string) ($permission['entity'] ?? '');
            $key = (string) ($permission['key'] ?? '');

            if (! in_array($entity, $entityKeys, true)) {
                throw InvalidModuleBlueprintException::forField(
                    "permissions.custom.{$key}.entity",
                    "La permission personnalisée « {$key} » cible l'entité « {$entity} », qui n'est pas déclarée dans ce blueprint."
                );
            }

            // Une ligne entièrement vide est écartée en amont par l'étape 3 :
            // ce qui arrive ici à moitié rempli est une vraie erreur de saisie,
            // à signaler tout de suite plutôt qu'à la génération.
            if ($key === '' || ($permission['label'] ?? '') === '') {
                throw InvalidModuleBlueprintException::forField(
                    "permissions.custom.{$entity}",
                    "Une permission personnalisée de l'entité « {$entity} » est incomplète : action et libellé sont tous deux requis."
                );
            }

            BlueprintIdentifiers::check(
                BlueprintIdentifiers::CLASS_NAME,
                $permission['entity'] ?? null,
                "permissions.custom.{$key}.entity",
                'une entité se désigne par sa clé en PascalCase (Car)'
            );

            BlueprintIdentifiers::check(
                BlueprintIdentifiers::SNAKE,
                $permission['key'] ?? null,
                "permissions.custom.{$key}.key",
                'une action de permission s\'écrit en minuscules, avec des underscores (publish, force_delete)'
            );

            if (in_array("{$entity}.{$key}", $seen, true)) {
                throw InvalidModuleBlueprintException::forField(
                    "permissions.custom.{$key}",
                    "La permission personnalisée « {$key} » est déclarée plus d'une fois sur l'entité « {$entity} »."
                );
            }

            $seen[] = "{$entity}.{$key}";
        }
    }

    /**
     * Refuse le blueprint dont `auto_crud` est coupé alors qu'une entité
     * expose une surface **autorisée** (admin ou API REST).
     *
     * Le module serait mort-né : `policy.stub` mappe toujours les cinq
     * méthodes CRUD, et les contrôleurs admin/API générés autorisent contre
     * elles (`can('viewAny', …)`, `can('update', $model)`) — sans les
     * permissions correspondantes dans le manifeste, personne ne peut passer.
     * La surface front est exclue : son contrôleur généré n'autorise rien,
     * ce sont des pages publiques.
     *
     * **Appelée depuis `fromJson()` seulement, jamais depuis
     * `fromDraftJson()`** (décision du 9 août 2026, suivi n° 103) : bloquer
     * aussi le brouillon enfermerait l'utilisateur, puisque `routes.admin`
     * vaut `true` par défaut — couper `auto_crud` à l'étape 3 ferait échouer
     * l'enregistrement avant qu'il ait pu atteindre l'étape 4 pour désactiver
     * l'admin. On explore librement dans le wizard, on ne peut jamais
     * *produire* un module mort-né.
     *
     * @param  array<string, mixed>  $permissions
     * @param  list<array<string, mixed>>  $entities
     */
    private static function validateSurfacesAgainstPermissions(array $permissions, array $entities): void
    {
        if (($permissions['auto_crud'] ?? true) !== false) {
            return;
        }

        // Défauts alignés sur `{Admin,Api}CrudGenerator::isEnabled()`.
        $guarded = ['admin' => true, 'api' => false];

        foreach ($entities as $entity) {
            $key = (string) ($entity['key'] ?? '?');

            foreach ($guarded as $surface => $default) {
                if ((bool) ($entity['routes'][$surface] ?? $default)) {
                    throw InvalidModuleBlueprintException::forField(
                        "entities.{$key}.routes.{$surface}",
                        "L'entité « {$key} » expose une surface {$surface} dont les contrôleurs générés autorisent contre sa policy, alors que « auto_crud » est désactivé : les permissions CRUD n'existeraient dans aucun rôle et bloqueraient tout le monde. Réactivez le CRUD automatique, ou désactivez cette surface."
                    );
                }
            }
        }
    }

    public function blueprintVersion(): int
    {
        return $this->data['blueprint_version'] ?? 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        return $this->data['identity'] ?? [];
    }

    public function name(): ?string
    {
        return $this->identity()['name'] ?? null;
    }

    public function title(): ?string
    {
        return $this->identity()['title'] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entities(): array
    {
        return $this->data['entities'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entity(string $key): ?array
    {
        foreach ($this->entities() as $entity) {
            if (($entity['key'] ?? null) === $key) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @return array{auto_crud?: bool, custom?: list<array<string, mixed>>}
     */
    public function permissions(): array
    {
        return $this->data['permissions'] ?? [];
    }

    public function autoCrudEnabled(): bool
    {
        return $this->permissions()['auto_crud'] ?? true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customPermissions(): array
    {
        return $this->permissions()['custom'] ?? [];
    }

    /**
     * @return array{emits?: list<string>, listens?: array<string, string>}
     */
    public function hooks(): array
    {
        return $this->data['hooks'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function hooksEmitted(): array
    {
        return $this->hooks()['emits'] ?? [];
    }

    /**
     * @return array<string, string> Nom du hook → nom court de classe (sans namespace).
     */
    public function hooksListened(): array
    {
        return $this->hooks()['listens'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminMenuItems(): array
    {
        return $this->data['menus']['admin'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function widgets(): array
    {
        return $this->data['widgets'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
