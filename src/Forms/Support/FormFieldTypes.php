<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

/**
 * Sous-ensemble du catalogue de champs (spec 02) que la spec 14 §2.2 ouvre
 * aux formulaires — un seul vocabulaire de champs dans tout le CMS (spec 14
 * §1), mais pas la totalité du catalogue : les types propres au contenu
 * éditorial (`richtext`, `slug`, `json`, `gallery`, `decimal`, `datetime`,
 * `time`) n'ont pas de sens dans un formulaire public, et `file`/`image`
 * restent hors catalogue pour l'instant — la spec 14 §5 exige un disque
 * *privé* dédié aux soumissions, jamais la médiathèque publique que
 * `Baobab\ContentTypes\Fields\Types\FileField`/`ImageField` ciblent ; le
 * champ fichier des formulaires est construit en Pass C (rendu front) avec
 * son propre mécanisme de stockage.
 */
final class FormFieldTypes
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [
            'text', 'textarea', 'email', 'tel', 'url', 'number',
            'date', 'select', 'radio', 'checkbox', 'checkboxes',
        ];
    }

    /**
     * Alias exposés par la spec 14 §2.2 vers les clés réelles du
     * `FieldRegistry` (spec 02) : `number` couvre `integer`, `checkbox` un
     * booléen unique, `checkboxes` un multi-choix — mêmes mécaniques, noms
     * plus parlants pour un formulaire public.
     *
     * @return array<string, string>
     */
    public static function registryAliases(): array
    {
        return [
            'number' => 'integer',
            'checkbox' => 'boolean',
            'checkboxes' => 'multiselect',
        ];
    }

    public static function registryKey(string $type): string
    {
        return self::registryAliases()[$type] ?? $type;
    }

    public static function isAllowed(string $type): bool
    {
        return in_array($type, self::allowed(), true);
    }
}
