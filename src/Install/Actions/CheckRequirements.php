<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\HostingProfile;
use Baobab\Install\Requirement;
use Baobab\Install\RequirementsReport;
use Illuminate\Filesystem\Filesystem;

/**
 * Étape 1 de l'installation (spec 15 §4) : ce qui bloque, ce qui avertit, et
 * le profil d'hébergement que les étapes suivantes consommeront (§4.1).
 *
 * L'Action est **relançable sur une instance déjà installée** : c'est ce que
 * fera `baobab:check`, qui rejoue la détection et la compare au profil du lock
 * (suivi n° 210). Elle ne modifie donc rien et ne suppose aucun état.
 *
 * **Sur les permissions, cette étape ne sait que constater.** Un dépôt par FTP
 * fait naître les fichiers sous le compte FTP et non sous celui de PHP, et
 * l'installateur ne peut rien y faire ; c'est l'amorce web (§2.2) qui supprime
 * le problème à la source, en décompressant depuis PHP.
 */
final class CheckRequirements
{
    /** Plancher de la v1 — spec 15 §8, décision 4. */
    public const MINIMUM_PHP = '8.4.0';

    /** Mémoire conseillée, jamais bloquante : c'est un avertissement. */
    public const ADVISED_MEMORY_BYTES = 256 * 1024 * 1024;

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = ['pdo', 'mbstring', 'intl', 'zip', 'curl', 'bcmath'];

    /** L'une ou l'autre suffit : le Core ne dépend pas d'une implémentation. */
    private const IMAGE_EXTENSIONS = ['gd', 'imagick'];

    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array<string, string>  $writablePaths  libellé => chemin absolu
     * @param  array<string, mixed>  $server  `$_SERVER` de la requête, vide en console
     */
    public function __invoke(string $publicPath, array $writablePaths, array $server = []): RequirementsReport
    {
        $requirements = [
            $this->php(),
            ...$this->extensions(),
            $this->imageExtension(),
            ...$this->writable($writablePaths),
            $this->memory(),
        ];

        return new RequirementsReport(
            requirements: $requirements,
            profile: HostingProfile::detect($publicPath, $server),
        );
    }

    private function php(): Requirement
    {
        return Requirement::blocking(
            key: 'php',
            label: 'Version de PHP',
            satisfied: version_compare(PHP_VERSION, self::MINIMUM_PHP, '>='),
            detail: PHP_VERSION.' détecté, '.self::MINIMUM_PHP.' minimum requis',
            remedy: 'Changez la version de PHP depuis le panneau de votre hébergement, puis rechargez cette page.',
        );
    }

    /**
     * @return list<Requirement>
     */
    private function extensions(): array
    {
        return array_map(
            fn (string $extension): Requirement => Requirement::blocking(
                key: 'ext.'.$extension,
                label: 'Extension '.$extension,
                satisfied: extension_loaded($extension),
                remedy: 'Activez l\'extension '.$extension.' depuis le panneau de votre hébergement.',
            ),
            self::REQUIRED_EXTENSIONS,
        );
    }

    private function imageExtension(): Requirement
    {
        $present = array_values(array_filter(self::IMAGE_EXTENSIONS, 'extension_loaded'));

        return Requirement::blocking(
            key: 'ext.image',
            label: 'Extension d\'images (gd ou imagick)',
            satisfied: $present !== [],
            detail: $present === [] ? null : implode(', ', $present),
            remedy: 'Activez gd ou imagick : sans l\'une des deux, aucune vignette ne peut être produite.',
        );
    }

    /**
     * @param  array<string, string>  $paths
     * @return list<Requirement>
     */
    private function writable(array $paths): array
    {
        $requirements = [];

        foreach ($paths as $label => $path) {
            $requirements[] = Requirement::blocking(
                key: 'writable.'.$label,
                label: 'Écriture dans '.$label,
                satisfied: $this->files->isDirectory($path) && $this->files->isWritable($path),
                detail: $path,
                remedy: 'Donnez les droits d\'écriture à l\'utilisateur qui exécute PHP. Si les fichiers ont été déposés par FTP, ils appartiennent au compte FTP et non à PHP.',
            );
        }

        return $requirements;
    }

    private function memory(): Requirement
    {
        $limit = $this->memoryLimitInBytes();

        return Requirement::advisory(
            key: 'memory',
            label: 'Mémoire disponible',
            // Une limite absente (-1) est illimitée, donc satisfaisante.
            satisfied: $limit === null || $limit >= self::ADVISED_MEMORY_BYTES,
            detail: $limit === null ? 'sans limite' : $this->humanBytes($limit).' (256 Mo conseillés)',
            remedy: 'Une installation aboutit souvent en deçà, mais la génération de vignettes et l\'import peuvent échouer.',
        );
    }

    private function memoryLimitInBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $unit = mb_strtolower(mb_substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / 1024 / 1024).' Mo'
            : round($bytes / 1024).' Ko';
    }
}
