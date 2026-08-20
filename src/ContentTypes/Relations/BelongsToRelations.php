<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Relations;

use Illuminate\Support\Str;

/**
 * Les relations d'un blueprint qui se saisissent dans **un seul champ** : celles
 * qui posent une colonne `{clé}_id` sur l'entité déclarante, autrement dit le
 * `belongsTo` ordinaire (spec 02 §5, suivi n° 140).
 *
 * Extraite ici parce que les deux chemins de rendu doivent s'accorder sur la
 * même définition : la fiche de Content Type la consomme à l'exécution, le
 * générateur du Studio à la génération. Deux listes de types écrites séparément
 * finiraient par diverger.
 *
 * **Hors périmètre, et c'est délibéré** (n° 169) :
 * - `many_to_many` n'a pas de colonne — sa saisie suppose un `sync()` dans les
 *   deux chemins de sauvegarde, qui ignorent aujourd'hui les relations ;
 * - `polymorphic` demande un couple `{clé}_type` / `{clé}_id`, donc deux
 *   saisies liées sur un ensemble de cibles ouvert.
 */
final class BelongsToRelations
{
    /**
     * Les types qui posent une colonne unique. `polymorphic` en pose une aussi,
     * mais jamais seule : il est écarté ici pour cette raison précise.
     */
    private const TYPES = ['one_to_one', 'one_to_many'];

    /**
     * @param  array<string, mixed>  $blueprint  blueprint de Content Type ou entité de module — les deux portent `relations[]` à l'identique
     * @return list<array{key: string, column: string, target: string, label: string, required: bool}>
     */
    public static function from(array $blueprint): array
    {
        $found = [];

        foreach ((array) ($blueprint['relations'] ?? []) as $relation) {
            if (! is_array($relation)) {
                continue;
            }

            $type = $relation['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                continue;
            }

            $key = (string) ($relation['key'] ?? '');
            $target = (string) ($relation['target'] ?? '');

            if ($key === '' || $target === '') {
                continue;
            }

            $found[] = [
                'key' => $key,
                'column' => "{$key}_id",
                'target' => $target,
                // Le libellé se dérive de la relation, pas de la colonne :
                // « Auteur » plutôt que « Auteur Id », qui laisserait fuiter le
                // schéma dans l'interface.
                'label' => Str::headline($key),
                'required' => (bool) ($relation['required'] ?? false),
            ];
        }

        return $found;
    }
}
