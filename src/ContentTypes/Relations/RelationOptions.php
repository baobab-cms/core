<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Relations;

use Illuminate\Database\Eloquent\Model;

/**
 * Les entrées sélectionnables d'une relation `belongsTo`, sous la forme
 * `identifiant => libellé` — prêtes à être passées telles quelles à
 * `<x-baobab::field.relation>` (spec 02 §5, suivi n° 140).
 *
 * Chargées ici et non dans le gabarit : une requête Eloquent dans une vue est
 * interdite (CLAUDE.md), et les deux chemins de rendu — la fiche de Content
 * Type et le CRUD généré par le Studio — doivent poser exactement la même
 * question à la base plutôt que d'en écrire chacun une version.
 *
 * **Écart assumé avec spec 04 §5** (consigné au n° 169) : la spec décrit une
 * sélection à *recherche asynchrone*, alors que la liste est ici chargée d'un
 * bloc. Le plafond ci-dessous borne le coût de cet écart au lieu de le
 * laisser croître avec la table — au-delà, la saisie reste possible mais
 * incomplète, ce qui est le motif même de la dette.
 */
final class RelationOptions
{
    /**
     * Nombre maximum d'entrées proposées. Volontairement bas : c'est un garde-fou
     * de performance, pas une pagination — la vraie réponse est la recherche
     * asynchrone de la spec.
     */
    public const LIMIT = 200;

    /**
     * `$modelClass` est reçu en `string` et vérifié ici plutôt que promis par
     * le type : il vient de `ContentType::modelClass()`, qui forge un FQCN par
     * convention sans garantir qu'une classe existe derrière — un Content Type
     * dont le module a été désinstallé en est l'exemple. Une cible qui n'est
     * pas un modèle rend une liste vide, comme une cible introuvable : l'écran
     * d'édition perd son sélecteur, il ne tombe pas.
     *
     * @return array<int|string, string>
     */
    public static function for(string $modelClass, ?string $labelColumn = null): array
    {
        if (! is_a($modelClass, Model::class, true)) {
            return [];
        }

        $labelColumn ??= self::deriveLabelColumn(new $modelClass);

        $query = $modelClass::query();

        if ($labelColumn !== null) {
            $query->orderBy($labelColumn);
        }

        $options = [];

        foreach ($query->limit(self::LIMIT)->get() as $entry) {
            /** @var Model $entry */
            $key = $entry->getKey();

            if (is_int($key) || is_string($key)) {
                $options[$key] = self::label($entry, $labelColumn);
            }
        }

        return $options;
    }

    /**
     * Colonne de libellé quand l'appelant n'en impose pas : le premier attribut
     * `fillable` qui n'est ni une colonne de convention (spec 02 §4.2), ni une
     * clé étrangère. C'est le chemin du code généré, qui ne connaît pas le
     * blueprint de sa cible à l'exécution ; la fiche de Content Type, elle,
     * passe explicitement le `title_field`, plus juste parce qu'il est déclaré.
     *
     * Faute de candidat, `null` — les entrées s'affichent alors par leur
     * identifiant, ce qui reste utilisable et ne masque aucune entrée.
     */
    private static function deriveLabelColumn(Model $model): ?string
    {
        $conventions = ['status', 'published_at', 'unpublish_at', 'author_id', 'uuid'];

        foreach ($model->getFillable() as $attribute) {
            if (in_array($attribute, $conventions, true) || str_ends_with($attribute, '_id')) {
                continue;
            }

            return $attribute;
        }

        return null;
    }

    /**
     * Le libellé d'une entrée : la colonne désignée si elle porte quelque chose
     * de lisible, sinon l'identifiant — jamais une ligne vide, qui laisserait
     * une option impossible à distinguer de sa voisine.
     */
    private static function label(Model $entry, ?string $labelColumn): string
    {
        $value = $labelColumn === null ? null : $entry->getAttribute($labelColumn);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '#'.((string) $entry->getKey());
    }
}
