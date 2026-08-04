<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Client CLI non interactif du Wizard Studio (spec-modules §5.3) : lit un
 * fichier blueprint JSON tel quel et le passe à `ModuleGenerator` — même
 * moteur de génération que le futur Studio graphique (Pass B), patron
 * `content-type:build`. Ne fait que générer sur disque, comme son homologue
 * Content Type — installation/activation restent des étapes séparées
 * (`module:install`/`module:activate`, déjà existantes).
 */
final class ModuleBuildCommand extends Command
{
    protected $signature = 'module:build {path : Chemin vers un fichier blueprint JSON}';

    protected $description = 'Construit un module à partir d\'un fichier blueprint JSON (Wizard Studio).';

    public function handle(ModuleGenerator $generator): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        $resolvedPath = File::exists($path) ? $path : base_path($path);

        if (! File::exists($resolvedPath)) {
            $this->error("Fichier blueprint introuvable : {$path}");

            return self::FAILURE;
        }

        try {
            $blueprint = ModuleBlueprint::fromJson((string) File::get($resolvedPath));
            $name = $generator($blueprint);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module [{$name}] construit avec succès.");
        $this->line('  Répertoire : '.$generator->moduleDir($name));

        return self::SUCCESS;
    }
}
