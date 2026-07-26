<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\ModuleDiscovery;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;
use Baobab\Themes\Validation\ThemeValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * `php artisan baobab:theme:package {name}` (spec 17 §7) — lint bloquant
 * (même `ThemeValidator` que `theme:validate`/`theme:lint`) puis archive
 * `.zip` du répertoire du thème, `node_modules/`, `.git/` et l'état de
 * génération (`.baobab-checksums.json`) exclus. Ne lance pas elle-même
 * `npm run build` (aucun précédent d'invocation Node/npm dans ce codebase
 * PHP, suivi n° 88) : le développeur build ses assets avant de packager,
 * la commande zippe l'état actuel du répertoire.
 */
final class ThemePackageCommand extends Command
{
    protected $signature = 'baobab:theme:package {name : The theme name (vendor/slug)} {--output= : Destination path for the archive}';

    protected $description = 'Package a theme into a distributable zip archive, blocked by a failing lint (spec 17 §7).';

    private const EXCLUDED_PREFIXES = ['node_modules/', '.git/'];

    private const EXCLUDED_FILES = ['.baobab-checksums.json'];

    public function handle(ModuleDiscovery $discovery, ThemeValidator $validator): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $discovered = $discovery->scan()->get($name);

        if ($discovered === null) {
            $this->error("Theme not found: {$name}.");

            return self::FAILURE;
        }

        if ($discovered->manifest->type() !== 'theme') {
            $this->error("Not a theme: {$name}.");

            return self::FAILURE;
        }

        try {
            $validator->assertValid($discovered->manifest, $discovered->path);
        } catch (ThemeValidationFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $sourcePath = rtrim(str_replace('\\', '/', $discovered->path), '/');
        $slug = Str::afterLast($name, '/');

        /** @var string|null $output */
        $output = $this->option('output');
        $outputPath = $output ?? dirname($sourcePath)."/{$slug}-{$discovered->manifest->version()}.zip";

        $this->buildArchive($sourcePath, $outputPath);

        $this->info("Theme [{$name}] packaged at {$outputPath}.");

        return self::SUCCESS;
    }

    private function buildArchive(string $sourcePath, string $outputPath): void
    {
        if (File::isFile($outputPath)) {
            File::delete($outputPath);
        }

        File::ensureDirectoryExists(dirname($outputPath));

        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive : {$outputPath}.");
        }

        foreach (File::allFiles($sourcePath) as $file) {
            $relative = Str::after(str_replace('\\', '/', $file->getPathname()), "{$sourcePath}/");

            if ($this->excluded($relative)) {
                continue;
            }

            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();
    }

    private function excluded(string $relative): bool
    {
        if (in_array($relative, self::EXCLUDED_FILES, true)) {
            return true;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
