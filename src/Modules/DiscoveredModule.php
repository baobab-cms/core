<?php

declare(strict_types=1);

namespace Baobab\Modules;

/**
 * Un module trouvé sur disque par ModuleDiscovery, avant toute installation.
 */
final readonly class DiscoveredModule
{
    public function __construct(
        public ModuleManifest $manifest,
        public string $path,
        public string $source,
    ) {}
}
