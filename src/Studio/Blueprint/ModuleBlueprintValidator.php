<?php

declare(strict_types=1);

namespace Baobab\Studio\Blueprint;

use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Opis\JsonSchema\Validator;

/**
 * Valide un blueprint de module contre le contrat de
 * resources/schemas/module-blueprint.schema.json (spec 05, spec-modules §5.4).
 */
final class ModuleBlueprintValidator
{
    private static ?object $schema = null;

    /**
     * @throws InvalidModuleBlueprintException
     */
    public function validate(string $json): void
    {
        $data = json_decode($json, associative: false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $result = (new Validator)->validate($data, $this->schema());
        $error = $result->error();

        if ($result->isValid() || $error === null) {
            return;
        }

        throw InvalidModuleBlueprintException::fromValidationError($error);
    }

    private function schema(): object
    {
        return self::$schema ??= json_decode((string) file_get_contents(self::schemaPath()));
    }

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 3).'/resources/schemas/module-blueprint.schema.json';
    }
}
