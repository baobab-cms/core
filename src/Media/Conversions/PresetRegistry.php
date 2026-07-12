<?php

declare(strict_types=1);

namespace Baobab\Media\Conversions;

/**
 * Registre des presets de conversion (spec 06 §4.1). Trois presets Core
 * enregistrés au boot (thumb/medium/large) ; modules et thèmes ajoutent les
 * leurs via `media_presets` de leur manifest — même patron que FieldRegistry.
 */
final class PresetRegistry
{
    /** @var array<string, array{width?: int, height?: int, fit: string, quality?: int}> */
    private array $presets = [];

    /**
     * @param  array{width?: int, height?: int, fit?: string, quality?: int}  $definition
     */
    public function register(string $name, array $definition): void
    {
        $this->presets[$name] = [...$definition, 'fit' => $definition['fit'] ?? 'contain'];
    }

    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    /**
     * @return array{width?: int, height?: int, fit: string, quality?: int}
     */
    public function get(string $name): array
    {
        return $this->presets[$name];
    }

    /**
     * @return array<string, array{width?: int, height?: int, fit: string, quality?: int}>
     */
    public function all(): array
    {
        return $this->presets;
    }
}
