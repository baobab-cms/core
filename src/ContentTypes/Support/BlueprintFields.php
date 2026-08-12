<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Support;

use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Illuminate\Support\Str;

/**
 * Ce qu'un blueprint dit de ses champs quand il s'agit de les **afficher**
 * (spec 02 §3.1, spec 19 §5.4, §5.10 amendement n° 17).
 *
 * Source de vérité unique et partagée, patron `BlueprintPermissions` : le
 * formulaire d'admin, les colonnes de liste et `<x-baobab::field.auto>`
 * consomment tous cette classe. Sans elle, chaque surface dérivait son propre
 * intitulé — l'admin humanisait la clé à la volée, le générateur de thèmes
 * écrivait la clé brute, et le thème par défaut aurait inventé un troisième
 * comportement.
 *
 * Deux règles gouvernent tout ce fichier :
 *
 * 1. **Une désignation prime toujours sur une déduction.** Le blueprint
 *    désigne son titre (`title_field`), son corps (`body_field`) et son image
 *    (`image_field`) ; les deux dernières sont facultatives et retombent sur
 *    une convention, parce qu'elles n'existaient pas avant le 12 août 2026 et
 *    qu'aucun blueprint écrit avant elles ne doit avoir à être édité.
 * 2. **La déduction est faillible, et c'est pour cela que la désignation
 *    existe.** Un type portant deux zones de texte (un corps et des notes
 *    internes) ou deux images (une couverture et un portrait) ne peut pas
 *    être deviné. Le repli sert les blueprints anciens, pas les cas
 *    ambigus.
 */
final class BlueprintFields
{
    /**
     * Types de champs pouvant porter la prose éditoriale, par ordre de
     * préférence — c'est déjà la règle que `ThemeGenerator` applique pour son
     * extrait de carte d'archive.
     *
     * @var list<string>
     */
    private const BODY_TYPES = ['richtext', 'textarea'];

    /**
     * Intitulé affiché d'un champ : le `label` déclaré, sinon la clé
     * humanisée. Le repli reste correct en anglais et bancal ailleurs — c'est
     * précisément ce que le `label` déclaré corrige, et pourquoi un blueprint
     * français produisait jusqu'ici un formulaire étiqueté « Featured Image ».
     *
     * @param  array<string, mixed>  $field
     */
    public static function label(array $field): string
    {
        $label = $field['label'] ?? null;

        if (is_string($label) && trim($label) !== '') {
            return $label;
        }

        return Str::headline((string) ($field['key'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    public static function titleKey(array $blueprint): ?string
    {
        $key = $blueprint['title_field'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Champ portant la prose éditoriale — le « contenu » d'une fiche publique
     * au sens de la spec 19 §5.4, qui n'est ni l'entrée entière ni l'ensemble
     * de ses valeurs.
     *
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>|null
     */
    public static function body(array $blueprint): ?array
    {
        return self::designated($blueprint, 'body_field')
            ?? self::firstOfType($blueprint, self::BODY_TYPES);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>|null
     */
    public static function image(array $blueprint): ?array
    {
        return self::designated($blueprint, 'image_field')
            ?? self::firstOfType($blueprint, ['image']);
    }

    /**
     * Tout champ qu'aucun autre rôle ne consomme : ni le titre, ni le corps,
     * ni l'image mise en avant.
     *
     * **Filtré sur `exposed_in_api`** : un champ que son type a explicitement
     * retiré de l'API n'a pas à réapparaître en HTML — ce sont les deux façons
     * publiques de lire une entrée. Sans ce filtre, un champ interne (notes de
     * rédaction, prix d'achat, référence fournisseur) s'afficherait sur la
     * page publique sans que personne l'ait demandé. Le drapeau a été écrit
     * pour l'API (spec 08 §2.1) et gagne ici un second sens, assumé faute
     * d'une propriété de visibilité dédiée.
     *
     * L'ordre est celui de déclaration du blueprint : c'est le seul ordre que
     * l'auteur du type ait exprimé.
     *
     * @param  array<string, mixed>  $blueprint
     * @return list<array<string, mixed>>
     */
    public static function rest(array $blueprint): array
    {
        $consumed = array_filter([
            self::titleKey($blueprint),
            self::body($blueprint)['key'] ?? null,
            self::image($blueprint)['key'] ?? null,
        ]);

        return array_values(array_filter(
            self::fields($blueprint),
            static fn (array $field): bool => ! in_array($field['key'] ?? null, $consumed, true)
                && ($field['exposed_in_api'] ?? true) !== false,
        ));
    }

    /**
     * Relations à afficher sur une fiche publique : les `many_to_many` vers un
     * autre Content Type, ce que la spec 19 §5.4 appelle des taxonomies —
     * Baobab n'a pas d'autre objet qui joue ce rôle.
     *
     * Deux exclusions, toutes deux volontaires. Les **autres cardinalités** :
     * un `one_to_many` n'a pas de borne (commentaires, lignes de commande) et
     * transformerait la fiche en déversoir, exigeant une pagination à
     * l'intérieur d'un template de détail — dans un thème sans JavaScript, et
     * qu'aucune spec ne décrit. Les **cibles hors Content Type** (`User` en
     * tête, liste fermée tenue par RelationTargetResolver) : l'auteur a son
     * traitement propre, et les autres relations vers un utilisateur sont
     * internes.
     *
     * @param  array<string, mixed>  $blueprint
     * @return list<array<string, mixed>>
     */
    public static function taxonomies(array $blueprint): array
    {
        $coreModels = RelationTargetResolver::coreModelKeys();

        /** @var list<array<string, mixed>> $relations */
        $relations = array_values((array) ($blueprint['relations'] ?? []));

        return array_values(array_filter(
            $relations,
            static fn (array $relation): bool => ($relation['type'] ?? null) === 'many_to_many'
                && ! in_array($relation['target'] ?? null, $coreModels, true),
        ));
    }

    /**
     * Champ nommé par une désignation de racine (`body_field`,
     * `image_field`), ou `null` si elle est absente — ou si elle nomme un
     * champ qui n'existe pas, cas que `ContentTypeBlueprint` refuse à la
     * validation mais qu'un blueprint déjà en base pourrait porter.
     *
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>|null
     */
    private static function designated(array $blueprint, string $designation): ?array
    {
        $key = $blueprint[$designation] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (self::fields($blueprint) as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  list<string>  $types  Par ordre de préférence.
     * @return array<string, mixed>|null
     */
    private static function firstOfType(array $blueprint, array $types): ?array
    {
        foreach ($types as $type) {
            foreach (self::fields($blueprint) as $field) {
                if (($field['type'] ?? null) === $type) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return list<array<string, mixed>>
     */
    private static function fields(array $blueprint): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = array_values((array) ($blueprint['fields'] ?? []));

        return $fields;
    }
}
