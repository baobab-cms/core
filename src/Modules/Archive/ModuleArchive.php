<?php

declare(strict_types=1);

namespace Baobab\Modules\Archive;

use Baobab\Modules\Exceptions\InvalidModuleArchiveException;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Lecture sûre d'une archive `.zip` de module (spec 01 §2, §4 — M8 point 9,
 * Pass B). Ne décide rien du cycle de vie : elle ouvre, refuse ce qui est
 * dangereux ou informe, et sait recopier son contenu à un endroit donné.
 *
 * **Toute la validation de forme se fait avant la moindre écriture.** Les
 * noms d'entrées sont vérifiés à l'ouverture, pas au moment de les écrire :
 * une archive qui contient un seul chemin non sûr est refusée en entier,
 * plutôt que partiellement extraite en ignorant l'entrée fautive. Une archive
 * qui tente de sortir de son dossier n'est pas maladroite.
 *
 * Ce que cette classe ne prétend pas faire : juger le **code** du module. Un
 * module est du PHP qui s'exécutera avec tous les privilèges de l'application ;
 * l'analyse statique et la signature restent hors v1 par la spec §7.1. Les
 * garde-fous ici sont d'intégrité, pas d'innocuité.
 */
final class ModuleArchive
{
    /** Signature d'un fichier ZIP, vérifiée sur les octets réels — jamais sur l'extension ou le MIME déclaré (patron `UploadFont`). */
    private const string MAGIC_BYTES = "PK\x03\x04";

    private const string MANIFEST = 'module.json';

    /**
     * @param  list<string>  $entries  Noms d'entrées, déjà normalisés et vérifiés.
     * @param  string  $rootPrefix  '' si le module est à la racine, 'dossier/' s'il est enveloppé.
     */
    private function __construct(
        private readonly ZipArchive $zip,
        private readonly array $entries,
        private readonly string $rootPrefix,
    ) {}

    /**
     * @throws InvalidModuleArchiveException
     */
    public static function open(string $path, int $maxSize): self
    {
        self::assertSize($path, $maxSize);
        self::assertMagicBytes($path);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw InvalidModuleArchiveException::unreadable();
        }

        $entries = self::readEntries($zip);

        return new self($zip, $entries, self::locateRoot($entries));
    }

    public function manifestJson(): string
    {
        $contents = $this->zip->getFromName($this->rootPrefix.self::MANIFEST);

        if ($contents === false) {
            throw InvalidModuleArchiveException::unreadable();
        }

        return $contents;
    }

    /**
     * Recopie le contenu du module dans `$directory`, en retirant le dossier
     * enveloppe s'il y en a un — nos propres archives mettent les fichiers à
     * la racine, celles téléchargées depuis une forge les enveloppent.
     *
     * Extraction entrée par entrée plutôt que `ZipArchive::extractTo()` : il
     * faut de toute façon dépouiller le préfixe, et écrire soi-même laisse le
     * contrôle de ce qui est réellement créé.
     */
    public function extractTo(string $directory): void
    {
        File::ensureDirectoryExists($directory);

        foreach ($this->entries as $entry) {
            if ($this->rootPrefix !== '' && ! str_starts_with($entry, $this->rootPrefix)) {
                continue;
            }

            $relative = substr($entry, strlen($this->rootPrefix));

            if ($relative === '' || str_ends_with($relative, '/')) {
                continue;
            }

            $contents = $this->zip->getFromName($entry);

            if ($contents === false) {
                throw InvalidModuleArchiveException::extractionFailed();
            }

            $destination = $directory.'/'.$relative;

            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, $contents);
        }
    }

    public function close(): void
    {
        $this->zip->close();
    }

    private static function assertSize(string $path, int $maxSize): void
    {
        $size = @filesize($path);

        if ($size === false) {
            throw InvalidModuleArchiveException::unreadable();
        }

        if ($size > $maxSize) {
            throw InvalidModuleArchiveException::tooLarge($maxSize);
        }
    }

    private static function assertMagicBytes(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw InvalidModuleArchiveException::unreadable();
        }

        $head = fread($handle, strlen(self::MAGIC_BYTES));
        fclose($handle);

        if ($head !== self::MAGIC_BYTES) {
            throw InvalidModuleArchiveException::notAZipArchive();
        }
    }

    /**
     * @return list<string>
     */
    private static function readEntries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false) {
                $zip->close();

                throw InvalidModuleArchiveException::unreadable();
            }

            self::assertSafeEntry($zip, $index, $name);

            $entries[] = str_replace('\\', '/', $name);
        }

        return $entries;
    }

    /**
     * Zip Slip et compagnie. Quatre refus : chemin absolu POSIX, chemin absolu
     * Windows (lettre de lecteur), remontée `..` dans n'importe quel segment,
     * et lien symbolique — ce dernier permettrait de faire pointer un fichier
     * du module vers n'importe quoi sur le serveur.
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

            throw InvalidModuleArchiveException::unsafeEntry($name);
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

        // Les 16 bits de poids fort portent le st_mode UNIX ; S_IFLNK = 0xA000.
        return (($attributes >> 16) & 0xF000) === 0xA000;
    }

    /**
     * Le module.json est soit à la racine (nos propres archives), soit sous un
     * unique dossier de premier niveau (archives de forge). Au-delà, on ne
     * devine pas : plusieurs manifests, ou aucun, sont deux refus distincts.
     *
     * @param  list<string>  $entries
     */
    private static function locateRoot(array $entries): string
    {
        $prefixes = [];

        foreach ($entries as $entry) {
            if (! str_ends_with($entry, self::MANIFEST)) {
                continue;
            }

            $prefix = substr($entry, 0, -strlen(self::MANIFEST));

            if ($prefix === '' || preg_match('#^[^/]+/$#', $prefix) === 1) {
                $prefixes[] = $prefix;
            }
        }

        $prefixes = array_values(array_unique($prefixes));

        if ($prefixes === []) {
            throw InvalidModuleArchiveException::manifestMissing();
        }

        if (count($prefixes) > 1) {
            throw InvalidModuleArchiveException::severalManifests();
        }

        return $prefixes[0];
    }
}
