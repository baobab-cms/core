<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Illuminate\Support\Facades\File;

/**
 * Anti-écrasement par checksum (spec 01 §5.4) : jamais d'écrasement silencieux
 * d'un fichier généré modifié à la main. Le registre des checksums voyage
 * avec le module généré (`.baobab-checksums.json`, portable git/zip).
 *
 * Le moteur sait **refuser** (`write()`) et, depuis la Pass C du wizard
 * Studio, **répondre à la question** (`conflicts()`) : quels fichiers seraient
 * écrasés, pour qu'un appelant puisse en proposer le diff plutôt que d'échouer
 * sèchement. L'écrasement reste un acte explicite (`$force`), jamais un
 * défaut.
 */
final class GeneratedFileChecksums
{
    private const REGISTRY_FILENAME = '.baobab-checksums.json';

    /**
     * Vrai si écrire ce fichier écraserait une modification manuelle : le
     * fichier existe et son contenu sur disque ne correspond pas au checksum
     * enregistré à la dernière génération — ou n'y figure pas du tout, ce qui
     * signifie qu'il n'a jamais été écrit par nous.
     */
    public function conflicts(string $moduleDir, string $relativePath): bool
    {
        $absolutePath = $moduleDir.'/'.$relativePath;

        if (! File::isFile($absolutePath)) {
            return false;
        }

        $recorded = $this->load($moduleDir)[$relativePath] ?? null;

        return $recorded === null
            || hash('sha256', (string) file_get_contents($absolutePath)) !== $recorded;
    }

    /**
     * Écrit $contents dans $moduleDir/$relativePath si c'est sûr : fichier
     * absent, ou présent avec un checksum inchangé depuis la dernière
     * génération. Lève une exception sans rien écraser si le fichier a été
     * modifié à la main depuis — sauf `$force`, réservé à une résolution de
     * conflit explicitement décidée par un humain.
     */
    public function write(string $moduleDir, string $relativePath, string $contents, bool $force = false): void
    {
        $absolutePath = $moduleDir.'/'.$relativePath;
        $registry = $this->load($moduleDir);

        if (! $force && $this->conflicts($moduleDir, $relativePath)) {
            throw GeneratedFileConflictException::forFile($relativePath);
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
