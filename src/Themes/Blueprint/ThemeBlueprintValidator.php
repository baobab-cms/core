<?php

declare(strict_types=1);

namespace Baobab\Themes\Blueprint;

use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;
use Opis\JsonSchema\Validator;

/**
 * Valide un blueprint de thème (`theme.json`) contre le contrat de
 * resources/schemas/theme-blueprint.schema.json (spec 17 §2) — patron exact
 * `Baobab\ContentTypes\Blueprint\BlueprintValidator`. Distinct de
 * `module.schema.json`, qui valide la sortie déjà générée/installée, pas
 * l'entrée du générateur.
 */
final class ThemeBlueprintValidator
{
    private static ?object $schema = null;

    /**
     * @throws InvalidThemeBlueprintException
     */
    public function validate(string $json): void
    {
        $data = json_decode($json, associative: false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidThemeBlueprintException::malformedJson(json_last_error_msg());
        }

        $result = (new Validator)->validate($data, $this->schema());
        $error = $result->error();

        if ($result->isValid() || $error === null) {
            return;
        }

        throw InvalidThemeBlueprintException::fromValidationError($error);
    }

    private function schema(): object
    {
        return self::$schema ??= json_decode((string) file_get_contents(self::schemaPath()));
    }

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 3).'/resources/schemas/theme-blueprint.schema.json';
    }
}
