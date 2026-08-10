<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Archive `.zip` distribuable du module décrit par un brouillon
 * (spec-modules §5.3, seconde sortie possible du Studio).
 *
 * **Ne touche jamais `/modules`.** Le ZIP est construit depuis
 * `ModuleGenerator::plan()` — la carte `chemin → contenu` extraite en Pass B5 —
 * et écrit avec `ZipArchive::addFromString()`, sans qu'aucun fichier n'existe
 * sur le disque au préalable. Télécharger est donc une sortie **alternative** à
 * « générer dans `/modules` », pas une action postérieure : un brouillon jamais
 * généré peut être empaqueté, et un brouillon déjà généré peut l'être à
 * nouveau sans rien réécrire.
 *
 * `.baobab-checksums.json` est volontairement absent de l'archive, pour la
 * même raison que dans `baobab:theme:package` : c'est un état de génération
 * local, pas un fichier du module distribué. Le destinataire de l'archive
 * repart donc d'un module « écrit à la main » du point de vue des checksums,
 * ce qui est le comportement voulu — il n'a pas hérité de notre historique de
 * génération.
 *
 * Validation **stricte** (`fromJson()`) : on n'empaquette pas plus qu'on ne
 * génère un blueprint incomplet ou incohérent.
 */
final class PackageModuleFromDraft
{
    public function __construct(
        private readonly ModuleGenerator $generator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return string Chemin absolu de l'archive créée (fichier temporaire, à
     *                servir puis supprimer par l'appelant).
     */
    public function __invoke(ModuleBlueprintDraft $draft): string
    {
        $blueprint = ModuleBlueprint::fromJson((string) json_encode($draft->migratedBlueprint()));

        $name = (string) $blueprint->name();
        $slug = Str::afterLast($name, '/');
        $version = (string) ($blueprint->identity()['version'] ?? '1.0.0');

        $archivePath = rtrim(sys_get_temp_dir(), '/\\').'/'.Str::slug("{$slug}-{$version}").'-'.Str::random(8).'.zip';

        $this->writeArchive($archivePath, $this->generator->plan($blueprint));

        $this->audit->record('studio.draft.packaged', $draft, ['module' => $name]);

        return $archivePath;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function writeArchive(string $archivePath, array $files): void
    {
        File::ensureDirectoryExists(dirname($archivePath));

        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive : {$archivePath}.");
        }

        ksort($files);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString($relativePath, $contents);
        }

        $zip->close();
    }
}
