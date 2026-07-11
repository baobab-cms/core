<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Blueprint;

use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Opis\JsonSchema\Validator;

/**
 * Valide un blueprint de Content Type contre le contrat de
 * ressources/schemas/content-type-blueprint.schema.json (spec 02 §1.2, §9).
 */
final class BlueprintValidator
{
    private static ?object $schema = null;

    /**
     * @throws InvalidBlueprintException
     */
    public function validate(string $json): void
    {
        $data = json_decode($json, associative: false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidBlueprintException::malformedJson(json_last_error_msg());
        }

        $result = (new Validator)->validate($data, $this->schema());
        $error = $result->error();

        if ($result->isValid() || $error === null) {
            return;
        }

        throw InvalidBlueprintException::fromValidationError($error);
    }

    private function schema(): object
    {
        return self::$schema ??= json_decode((string) file_get_contents(self::schemaPath()));
    }

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 3).'/ressources/schemas/content-type-blueprint.schema.json';
    }
}
