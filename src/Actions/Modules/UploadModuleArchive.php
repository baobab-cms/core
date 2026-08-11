<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Modules\Archive\ModuleArchive;
use Baobab\Modules\Exceptions\InvalidManifestException;
use Baobab\Modules\Exceptions\InvalidModuleArchiveException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleManifest;
use Baobab\Modules\ModuleUploadPaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Dépose un module fourni en archive `.zip` à l'emplacement où la découverte
 * le trouvera (spec 01 §2 : « `composer require` **ou upload d'un `.zip` via
 * l'admin** », §4 : « Téléchargement — via Composer ou upload ZIP »).
 *
 * **N'installe pas, n'active pas.** Le cycle de vie de la spec §3 commence par
 * un état « téléchargé » où « le code est présent mais inerte » : cette Action
 * produit exactement cet état, et le module apparaît alors dans l'écran
 * « Modules » avec un bouton Installer, comme n'importe quel module déposé à
 * la main dans `/modules`. Faire les trois d'un coup ferait de l'upload un
 * chemin d'installation parallèle, avec ses propres validations à maintenir.
 *
 * L'extraction se fait dans un dossier de transit, puis le dossier complet est
 * déplacé d'un bloc. Sans ça, une archive interrompue en cours d'extraction
 * laisserait un module à moitié écrit, que `ModuleDiscovery` listerait comme
 * découvrable.
 *
 * **Ce transit vit dans la racine de destination, jamais dans
 * `sys_get_temp_dir()`.** `File::moveDirectory()` est un `@rename()`, et
 * `rename()` échoue avec `EXDEV` dès que la source et la cible sont sur deux
 * systèmes de fichiers différents. Or `/tmp` est un tmpfs par défaut sur Debian
 * et Ubuntu récents, et php-fpm reçoit souvent un `/tmp` privé via
 * `PrivateTmp=yes` : extraire dans le temp système puis déplacer vers
 * `modules/` échouait donc sur la configuration Linux majoritaire, en silence
 * puisque le `@` avale le warning. Défaut relevé le 11 août 2026 sur le serveur
 * de recette, invisible sous Windows où tout vit sur le même volume (suivi
 * n° 123). Stager dans la racine cible rend le déplacement intra-partition par
 * construction, sans rien coûter à la propriété « tout ou rien ».
 *
 * Le nom du transit commence par un point : `ModuleDiscovery` scanne par
 * `glob(..., GLOB_ONLYDIR)`, qui n'apparie pas les entrées commençant par un
 * point. Un résidu laissé par un processus tué reste donc invisible au produit.
 *
 * Ce que cette Action ne vérifie pas : le **code**. Un module est du PHP qui
 * s'exécutera avec tous les privilèges de l'application ; analyse statique et
 * signature restent hors v1 (spec §7.1). Les garde-fous sont d'intégrité.
 */
final class UploadModuleArchive
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string  $archivePath  Chemin absolu de l'archive téléversée.
     * @return string Le nom du module déposé (`vendor/slug`), à passer à InstallModule.
     *
     * @throws InvalidModuleArchiveException
     * @throws InvalidManifestException
     */
    public function __invoke(string $archivePath): string
    {
        $archive = ModuleArchive::open($archivePath, self::maxSize());

        try {
            // Le manifest est validé contre le schéma **avant** la moindre
            // écriture : une archive au manifest cassé ne doit rien laisser.
            $manifest = ModuleManifest::fromJson($archive->manifestJson());

            $name = $manifest->name();
            $target = self::targetDirectory($manifest);

            $this->assertAvailable($name, $target);

            // La racine doit exister **avant** le transit, qui vit dedans : sur
            // une installation neuve, `modules/` n'a jamais été créé, et
            // `rename()` échouerait de toute façon sans son dossier parent.
            $root = dirname($target);
            File::ensureDirectoryExists($root);

            $staging = self::stagingDirectory($root);

            try {
                $archive->extractTo($staging);

                // `moveDirectory()` signale son échec par un `false` qu'il
                // serait facile d'ignorer. Le transit étant désormais dans la
                // même racine, il ne reste qu'une cause plausible — les droits
                // d'écriture — et le message la nomme.
                if (! File::moveDirectory($staging, $target)) {
                    throw InvalidModuleArchiveException::depositFailed($root);
                }
            } finally {
                File::deleteDirectory($staging);
            }

            $this->audit->record('module.uploaded', null, [
                'module' => $name,
                'type' => $manifest->type(),
                'version' => $manifest->version(),
                'path' => $target,
            ]);

            Hook::action('baobab.module.uploaded', $name, $target);

            return $name;
        } finally {
            $archive->close();
        }
    }

    private function assertAvailable(string $name, string $target): void
    {
        if (Module::where('name', $name)->exists()) {
            throw InvalidModuleArchiveException::alreadyInstalled($name);
        }

        // Refuser d'écraser vaut aussi pour un dossier qu'aucune ligne
        // `modules` ne revendique : un module déposé à la main mais jamais
        // installé est du travail de quelqu'un, pas un emplacement libre.
        if (File::exists($target)) {
            throw InvalidModuleArchiveException::directoryTaken(basename($target));
        }
    }

    public static function targetDirectory(ModuleManifest $manifest): string
    {
        $root = $manifest->type() === 'theme'
            ? ModuleUploadPaths::themes()
            : ModuleUploadPaths::modules();

        return $root.'/'.self::directorySlug($manifest->name());
    }

    /**
     * Même convention que les modules générés (`ModuleGenerator::moduleDir()`)
     * et que les assets de thème publiés : `vendor/slug` → `vendor-slug`.
     */
    public static function directorySlug(string $name): string
    {
        return Str::slug(str_replace('/', '-', $name));
    }

    /**
     * @param  string  $root  Racine de destination — le transit y vit, pour que
     *                        le `rename()` final ne traverse aucun montage.
     */
    private static function stagingDirectory(string $root): string
    {
        return $root.'/.baobab-upload-'.Str::random(12);
    }

    private static function maxSize(): int
    {
        return ModuleUploadPaths::maxSize();
    }
}
