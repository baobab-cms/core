<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

/**
 * Sous-ensemble du catalogue de champs (spec 02) que la spec 14 §2.2 ouvre
 * aux formulaires — un seul vocabulaire de champs dans tout le CMS (spec 14
 * §1), mais pas la totalité du catalogue : les types propres au contenu
 * éditorial (`richtext`, `slug`, `json`, `gallery`, `decimal`, `datetime`,
 * `time`) n'ont pas de sens dans un formulaire public, et `image` reste hors
 * catalogue (absent de la liste spec 14 §2.2).
 *
 * `file` n'apparaît **pas** dans `allowed()` : comme `consent`, c'est un type
 * spécial traité en dehors du `FieldRegistry` (spec 02) — la spec 14 §5 exige
 * un disque *privé* dédié aux soumissions, jamais la médiathèque publique que
 * `Baobab\ContentTypes\Fields\Types\FileField` cible. `FormBlueprint` le
 * valide directement, `FormEntryRules` construit ses règles Laravel natives
 * (`file`/`mimetypes`/`max`) sans passer par cette classe (Pass C3).
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
