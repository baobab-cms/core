<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Modules\Exceptions\InvalidManifestException;
use Opis\JsonSchema\Validator;

/**
 * Valide un module.json contre le contrat de resources/schemas/module.schema.json
 * (spec 01 §2.2, spec 03 §2.1). Le schéma JSON reste la référence documentaire ;
 * cette classe se contente de l'exécuter via opis/json-schema.
 */
final class ManifestValidator
{
    private static ?object $schema = null;

    /**
     * @throws InvalidManifestException
     */
    public function validate(string $json): void
    {
        $data = json_decode($json, associative: false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidManifestException::malformedJson(json_last_error_msg());
        }

        $result = (new Validator)->validate($data, $this->schema());
        $error = $result->error();

        if ($result->isValid() || $error === null) {
            return;
        }

        throw InvalidManifestException::fromValidationError($error);
    }

    private function schema(): object
    {
        return self::$schema ??= json_decode((string) file_get_contents(self::schemaPath()));
    }

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 2).'/resources/schemas/module.schema.json';
    }
}
