<?php

declare(strict_types=1);

namespace Baobab\Imports\Support;

use Baobab\Imports\Exceptions\InvalidImportArchiveException;
use ZipArchive;

/**
 * Lecture sûre d'une archive d'export de contenu (spec 12 §5.1, cadrage Pass
 * F2, suivi n° 328) — patron de sécurité de `Baobab\Modules\Archive\ModuleArchive`
 * (magic bytes vérifiés sur les octets réels, taille max, anti-Zip-Slip entrée
 * par entrée), jamais la classe elle-même : le manifeste est nommé
 * différemment (`manifest.json`, pas `module.json`) et l'archive se lit
 * entrée par entrée (`content/*.ndjson`, `blueprints/*.json`,
 * `media/files/*`), jamais extraite en bloc vers un répertoire.
 *
 * Chaque entrée lue est vérifiée contre le sha256 déclaré dans
 * `manifest.json.checksums` (sauf le manifeste lui-même, jamais checksumé
 * par construction — `Baobab\System\Actions\ExportContent::buildEntries()`).
 * Une archive altérée après export est refusée avant d'écrire quoi que ce
 * soit, jamais silencieusement acceptée.
 */
final class ImportArchiveReader
{
    private const string MAGIC_BYTES = "PK\x03\x04";

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function __construct(
        private readonly ZipArchive $zip,
        private readonly array $manifest,
    ) {}

    public static function open(string $path, int $maxSize): self
    {
        self::assertSize($path, $maxSize);
        self::assertMagicBytes($path);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw InvalidImportArchiveException::unreadable();
        }

        self::assertSafeEntries($zip);

        $manifestJson = $zip->getFromName('manifest.json');

        if ($manifestJson === false) {
            $zip->close();

            throw InvalidImportArchiveException::manifestMissing();
        }

        /** @var mixed $manifest */
        $manifest = json_decode($manifestJson, associative: true);

        if (! is_array($manifest)) {
            $zip->close();

            throw InvalidImportArchiveException::manifestMalformed();
        }

        /** @var array<string, mixed> $manifest */
        return new self($zip, $manifest);
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return $this->manifest;
    }

    public function formatVersion(): string
    {
        return (string) ($this->manifest['manifest_version'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function contentTypeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = (array) ($this->manifest['content_types'] ?? []);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public function blueprint(string $key): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($this->entry("blueprints/{$key}.json"), associative: true);

        if (! is_array($decoded)) {
            throw InvalidImportArchiveException::blueprintMalformed($key);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contentRecords(string $key): array
    {
        return $this->ndjson("content/{$key}.ndjson");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mediaMetadata(): array
    {
        if (! $this->has('media/metadata.ndjson')) {
            return [];
        }

        return $this->ndjson('media/metadata.ndjson');
    }

    /**
     * `media/metadata.ndjson` ne porte jamais `path` (instance-spécifique,
     * jamais portable) — l'extension réelle du fichier archivé (dérivée de
     * `Media::$path` par `ExportContent::buildMedia()`) ne peut donc pas être
     * recalculée fiablement depuis `file_name` seul (capitalisation, cas
     * limites). Résolue ici par préfixe sur les entrées réelles de l'archive
     * plutôt que par convention supposée entre export et import.
     */
    public function mediaFileBytes(string $uuid): string
    {
        $prefix = "media/files/{$uuid}.";

        for ($index = 0; $index < $this->zip->numFiles; $index++) {
            $name = $this->zip->getNameIndex($index);

            if ($name !== false && str_starts_with($name, $prefix)) {
                return $this->entry($name);
            }
        }

        throw InvalidImportArchiveException::entryMissing($prefix.'*');
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ndjson(string $name): array
    {
        $contents = trim($this->entry($name));

        if ($contents === '') {
            return [];
        }

        return array_map(function (string $line) use ($name): array {
            /** @var mixed $decoded */
            $decoded = json_decode($line, associative: true);

            if (! is_array($decoded)) {
                throw InvalidImportArchiveException::recordMalformed($name);
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }, explode("\n", $contents));
    }

    private function has(string $name): bool
    {
        return $this->zip->locateName($name) !== false;
    }

    private function entry(string $name): string
    {
        $contents = $this->zip->getFromName($name);

        if ($contents === false) {
            throw InvalidImportArchiveException::entryMissing($name);
        }

        $this->assertChecksum($name, $contents);

        return $contents;
    }

    private function assertChecksum(string $name, string $contents): void
    {
        /** @var array<string, string> $checksums */
        $checksums = (array) ($this->manifest['checksums'] ?? []);
        $expected = $checksums[$name] ?? null;

        if ($expected === null) {
            return;
        }

        if (! hash_equals($expected, hash('sha256', $contents))) {
            throw InvalidImportArchiveException::checksumMismatch($name);
        }
    }

    private static function assertSize(string $path, int $maxSize): void
    {
        $size = @filesize($path);

        if ($size === false) {
            throw InvalidImportArchiveException::unreadable();
        }

        if ($size > $maxSize) {
            throw InvalidImportArchiveException::tooLarge($maxSize);
        }
    }

    private static function assertMagicBytes(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw InvalidImportArchiveException::unreadable();
        }

        $head = fread($handle, strlen(self::MAGIC_BYTES));
        fclose($handle);

        if ($head !== self::MAGIC_BYTES) {
            throw InvalidImportArchiveException::notAZipArchive();
        }
    }

    private static function assertSafeEntries(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false) {
                $zip->close();

                throw InvalidImportArchiveException::unreadable();
            }

            self::assertSafeEntry($zip, $index, $name);
        }
    }

    /**
     * Zip Slip et compagnie (patron exact `ModuleArchive::assertSafeEntry()`) :
     * chemin absolu POSIX, chemin absolu Windows (lettre de lecteur), remontée
     * `..` dans n'importe quel segment, lien symbolique.
     */
    private static function assertSafeEntry(ZipArchive $zip, int $index, string $name): void
    {
        $normalized = str_replace('\\', '/', $name);

        $unsafe = str_starts_with($normalized, '/')
            || preg_match('#^[A-Za-z]:#', $normalized) === 1
            || in_array('..', explode('/', $normalized), true)
            || self::isSymlink($zip, $index);

        if ($unsafe) {
            $zip->close();

            throw InvalidImportArchiveException::unsafeEntry($name);
        }
    }

    private static function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;

        if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) !== true) {
            return false;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        return (($attributes >> 16) & 0xF000) === 0xA000;
    }
}
