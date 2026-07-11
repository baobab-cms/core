<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields;

/**
 * Contrat d'un type de champ (spec 02 §3.1). Chaque type de champ Core ou de
 * module s'enregistre dans le FieldRegistry et déclare tout ce que le
 * système doit savoir sur lui.
 *
 * Écart assumé avec la spec : `column(Blueprint $table, Field $field): void`
 * y est illustré comme une manipulation d'un Blueprint vivant. M3 génère des
 * migrations en fichiers statiques et lisibles (spec 02 §1.2) — il n'y a pas
 * de Blueprint vivant au moment de la génération, seulement du code PHP à
 * émettre. `columnDefinition()` retourne donc la ligne `$table->...(...)`
 * telle quelle, à insérer dans le stub de migration.
 */
abstract class FieldType
{
    abstract public static function key(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    abstract public function columnDefinition(string $name, array $options): string;

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    abstract public function rules(string $name, array $options): array;

    /**
     * @param  array<string, mixed>  $options
     */
    abstract public function cast(array $options): ?string;

    abstract public function formComponent(): string;

    abstract public function displayComponent(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    abstract public function toApi(mixed $value, array $options): mixed;

    /**
     * @param  array<string, mixed>  $options
     */
    abstract public function graphqlType(array $options): string;

    /**
     * Règles Laravel validant les `options` de ce champ tel que déclaré dans
     * le blueprint (pas les données du contenu — voir rules()). Par défaut,
     * aucune contrainte.
     *
     * @return array<string, list<string>>
     */
    public function optionsRules(): array
    {
        return [];
    }
}
