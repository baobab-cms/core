<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields;

use Baobab\ContentTypes\Exceptions\UnknownFieldTypeException;

/**
 * Registre central des types de champs (spec 02 §3.1). Les 14 types Core
 * sont enregistrés au boot ; un module peut en ajouter via
 * `FieldRegistry::register(MapPointField::class)` dans son provider.
 */
final class FieldRegistry
{
    /** @var array<string, class-string<FieldType>> */
    private array $types = [];

    /**
     * @param  class-string<FieldType>  $fieldType
     */
    public function register(string $fieldType): void
    {
        $this->types[$fieldType::key()] = $fieldType;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function resolve(string $key): FieldType
    {
        if (! $this->has($key)) {
            throw UnknownFieldTypeException::forKey($key);
        }

        return app($this->types[$key]);
    }

    /**
     * @return array<string, class-string<FieldType>>
     */
    public function all(): array
    {
        return $this->types;
    }
}
