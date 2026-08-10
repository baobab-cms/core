<?php

declare(strict_types=1);

namespace Baobab\Studio\Support;

use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;

/**
 * Motifs des identifiants d'un blueprint de module, et leur vérification.
 *
 * **Pourquoi cette classe existe** : `module-blueprint.schema.json` porte ces
 * motifs, mais le schéma n'est appliqué qu'à la génération
 * (`ModuleBlueprint::fromJson()`) — jamais pendant la saisie, où le
 * constructeur permissif `fromDraftJson()` accepte tout. Un libellé humain
 * saisi dans un champ « clé » (« Contenu » au lieu de `contenu`) traversait
 * donc les huit étapes du wizard sans un mot, pour n'être refusé qu'au
 * récapitulatif — loin de l'écran où la faute a été commise. Défaut réel
 * signalé en vérification navigateur de la Pass B5.
 *
 * **Ce n'est pas en contradiction avec la permissivité du brouillon** : celle-ci
 * porte sur les sections *absentes* (un blueprint en cours de saisie n'a pas à
 * être complet), jamais sur les valeurs *malformées*. Un identifiant mal formé
 * est faux à toutes les étapes, il n'y a aucune raison d'attendre pour le dire.
 *
 * Les constantes recopient le schéma, qui reste la source de vérité ; un test
 * dédié (`BlueprintIdentifiersTest`) vérifie qu'elles n'en divergent pas.
 */
final class BlueprintIdentifiers
{
    /** Clé d'entité (PascalCase) — `entities[].key`, `permissions.custom[].entity`, `widgets[].class_name`, `hooks.listens[*]`. */
    public const CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    /** Identifiant snake_case — `entities[].table`, `entities[].fields[].key`, `entities[].relations[].key`, `permissions.custom[].key`. */
    public const SNAKE = '/^[a-z][a-z0-9_]*$/';

    /** Nom de hook pointé — `hooks.emits[]` et les clés de `hooks.listens`. */
    public const HOOK_NAME = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    /** Clé de widget pointée, tirets autorisés après le premier segment — `widgets[].key`. */
    public const WIDGET_KEY = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9-]*)+$/';

    /**
     * Vérifie une valeur et lève avec le chemin exact du champ fautif, dans le
     * même format que les autres cross-validations (`entities.Car.fields.x`).
     * Une valeur vide est laissée passer : c'est l'affaire du schéma complet
     * (champ requis), pas celle du motif — un brouillon a le droit d'être
     * incomplet.
     *
     * @throws InvalidModuleBlueprintException
     */
    public static function check(string $pattern, mixed $value, string $field, string $expectation): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match($pattern, $value) !== 1) {
            throw InvalidModuleBlueprintException::forField(
                $field,
                "« {$value} » n'est pas un identifiant valide ici : {$expectation}."
            );
        }
    }
}
