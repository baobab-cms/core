<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator\Profiles;

use Baobab\Studio\Blueprint\ModuleBlueprint;

/**
 * Les **conventions** d'une famille de modules générés, extraites du moteur
 * (M8 point 2, Pass A — suivi n° 157). Le moteur (`ModuleGenerator`) sait
 * assembler un module à partir d'un blueprint ; il ne sait pas, et n'a pas à
 * savoir, qu'un Content Type porte le préfixe `ct_`, un socle de colonnes
 * éditoriales (spec 02 §4.2), un fragment GraphQL et aucune surface générée,
 * là où un module du Studio porte des contrôleurs, des routes et des widgets.
 *
 * Chaque méthode répond à **une** question de convention, et l'ensemble forme
 * l'énumération complète de ce qui séparait les deux générateurs avant leur
 * fusion. `ContentTypeProfile` étend `ModuleProfile` plutôt que de le doubler :
 * la spec-modules §6 énonce le Content Type builder comme « un cas particulier
 * simplifié du Studio », et l'héritage dit exactement cela — un module, plus
 * les conventions de contenu.
 *
 * **Où vit ce contrat, et pourquoi ici.** Le moteur est resté auprès du format
 * de blueprint qu'il consomme (`Baobab\Studio\Blueprint\ModuleBlueprint`) :
 * l'unification du *format* est la moitié explicitement différée du point 2
 * (n° 157), et déplacer le moteur avant elle reviendrait à choisir un domicile
 * sur un découpage qui n'est pas encore stabilisé. La dépendance qui compte
 * reste descendante : catalogue de champs et primitives de rendu
 * (`ContentTypes\Fields`, `ContentTypes\Generator`) ← moteur ← adaptateurs
 * (`ContentTypes\Actions`, `Studio\Actions`).
 */
interface GenerationProfile
{
    /**
     * Valeur du champ `type` du manifeste — `module` ou `content-type`.
     */
    public function manifestType(): string;

    /**
     * Racine du module sur disque, dérivée de son nom `vendor/slug`.
     */
    public function moduleDir(string $name): string;

    /**
     * Namespace racine du code généré.
     */
    public function rootNamespace(string $name, ModuleBlueprint $blueprint): string;

    /**
     * Nom court de la classe du Service Provider (sans namespace).
     */
    public function providerClass(string $slug, ModuleBlueprint $blueprint): string;

    /**
     * Préfixe des permissions d'une entité (`fleet.cars`, `content.car`).
     */
    public function permissionPrefix(string $slug, string $entityKey): string;

    /**
     * Préfixe des tables pivots générées pour un `many_to_many`.
     */
    public function pivotPrefix(): string;

    /**
     * Les migrations de création sont-elles nommées d'après le **graphe de
     * dépendances** (`MigrationOrder`, correctif du n° 120 livré par le n° 151)
     * plutôt que d'après une horloge et un tirage aléatoire ?
     *
     * Vrai pour un module du Studio. **Faux pour un Content Type**, et c'est
     * une dette assumée, pas un oubli : voir le suivi n° 160 pour la mesure du
     * coût et le patron de correction déjà retenu par ce dépôt.
     */
    public function ranksMigrations(): bool;

    /**
     * Colonnes structurelles écrites **avant** les colonnes de champs.
     *
     * @param  array<string, mixed>  $entity
     */
    public function leadingColumns(array $entity): string;

    /**
     * Colonnes structurelles écrites **après** les colonnes de relations.
     *
     * @param  array<string, mixed>  $entity
     */
    public function trailingColumns(array $entity): string;

    /**
     * Traits du modèle et leurs `use` de tête.
     *
     * @param  array<string, mixed>  $entity
     * @return array{imports: string, use: string}
     */
    public function modelTraits(array $entity): array;

    /**
     * Casts structurels, écrits avant ceux dérivés des champs.
     *
     * @param  array<string, mixed>  $entity
     */
    public function leadingCasts(array $entity): string;

    /**
     * Colonnes `fillable` structurelles, écrites avant celles des champs.
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    public function leadingFillable(array $entity): array;

    /**
     * Méthodes ajoutées au modèle après `casts()` (`toSearchableArray()`…).
     *
     * @param  array<string, mixed>  $entity
     */
    public function modelMethods(array $entity): string;

    /**
     * Contenu de la policy de l'entité — les deux familles n'ont pas la même
     * (own/any adossé à `author_id` pour un Content Type, CRUD simple pour un
     * module du Studio, qui n'a pas cette colonne).
     *
     * @param  array<string, mixed>  $entity
     */
    public function policy(array $entity, string $namespace, string $permissionPrefix, ModuleBlueprint $blueprint): string;

    /**
     * Fichiers du Service Provider, chemin relatif → contenu.
     *
     * @return array<string, string>
     */
    public function providerFiles(string $namespace, string $slug, ModuleBlueprint $blueprint): array;

    /**
     * Fichiers supplémentaires propres à une entité (fragment GraphQL et son
     * résolveur, pour un Content Type). Vide par défaut.
     *
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    public function extraEntityFiles(array $entity, string $namespace): array;

    /**
     * Le profil génère-t-il les surfaces à la carte — couche d'actions, CRUD
     * admin/front/API, écouteurs de hooks, widgets ? Un Content Type répond
     * non : son admin est rendu par le `ContentController` générique du Core
     * et son API par les routes génériques, aucun contrôleur n'est écrit.
     */
    public function generatesSurfaces(): bool;

    /**
     * Permissions déclarées au manifeste.
     *
     * @return list<array{key: string, label: string}>
     */
    public function permissions(ModuleBlueprint $blueprint, string $slug): array;

    /**
     * Bloc `menus` du manifeste, ou `null` s'il n'y en a pas.
     *
     * @return array{admin: list<array<string, mixed>>}|null
     */
    public function menus(ModuleBlueprint $blueprint): ?array;
}
