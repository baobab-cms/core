<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Illuminate\Support\Facades\File;

/**
 * Anti-écrasement par checksum (spec 01 §5.4) : jamais d'écrasement silencieux
 * d'un fichier généré modifié à la main. Le registre des checksums voyage
 * avec le module généré (`.baobab-checksums.json`, portable git/zip).
 */
final class GeneratedFileChecksums
{
    private const REGISTRY_FILENAME = '.baobab-checksums.json';

    /**
     * Écrit $contents dans $moduleDir/$relativePath si c'est sûr : fichier
     * absent, ou présent avec un checksum inchangé depuis la dernière
     * génération. Lève une exception sans rien écraser si le fichier a été
     * modifié à la main depuis.
     */
    public function write(string $moduleDir, string $relativePath, string $contents): void
    {
        $absolutePath = $moduleDir.'/'.$relativePath;
        $registry = $this->load($moduleDir);

        if (File::isFile($absolutePath)) {
            $recorded = $registry[$relativePath] ?? null;
            $onDisk = hash('sha256', (string) file_get_contents($absolutePath));

            if ($recorded === null || $onDisk !== $recorded) {
                throw GeneratedFileConflictException::forFile($relativePath);
            }
        }

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $contents);

        $registry[$relativePath] = hash('sha256', $contents);
        $this->save($moduleDir, $registry);
    }

    /**
     * @return array<string, string>
     */
    private function load(string $moduleDir): array
    {
        $path = $moduleDir.'/'.self::REGISTRY_FILENAME;

        if (! File::isFile($path)) {
            return [];
        }

        /** @var array<string, string> $registry */
        $registry = json_decode((string) file_get_contents($path), associative: true) ?? [];

        return $registry;
    }

    /**
     * @param  array<string, string>  $registry
     */
    private function save(string $moduleDir, array $registry): void
    {
        ksort($registry);

        File::put(
            $moduleDir.'/'.self::REGISTRY_FILENAME,
            (string) json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
