<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Actions\BuildContentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Client CLI non interactif du pipeline de création de Content Type (spec 02
 * §2.1) : lit un fichier blueprint JSON tel quel et le passe à BuildContentType
 * — même validation, même génération que l'admin ou content-type:make.
 */
final class ContentTypeBuildCommand extends Command
{
    protected $signature = 'content-type:build {path : Chemin vers un fichier blueprint JSON}';

    protected $description = 'Construit un Content Type à partir d\'un fichier blueprint JSON.';

    public function handle(BuildContentType $build): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        $resolvedPath = File::exists($path) ? $path : base_path($path);

        if (! File::exists($resolvedPath)) {
            $this->error("Fichier blueprint introuvable : {$path}");

            return self::FAILURE;
        }

        try {
            $contentType = $build(File::get($resolvedPath));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Content Type [{$contentType->key}] construit avec succès.");
        $this->line("  Table : {$contentType->table_name}");
        $this->line("  Module : {$contentType->module?->name}");

        return self::SUCCESS;
    }
}
