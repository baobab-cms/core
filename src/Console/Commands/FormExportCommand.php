<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Forms\Actions\ExportForm;
use Baobab\Forms\Models\Form;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `baobab:forms:export {slug}` (spec 14 §10) — patron `theme:package`
 * (chemin par défaut dérivé, `--output` pour le choisir).
 */
final class FormExportCommand extends Command
{
    protected $signature = 'baobab:forms:export {slug} {--output= : Chemin du fichier JSON produit}';

    protected $description = 'Exporte un formulaire en JSON (workflow agence staging → prod).';

    public function handle(ExportForm $action): int
    {
        /** @var string $slug */
        $slug = $this->argument('slug');
        $form = Form::query()->where('slug', $slug)->first();

        if ($form === null) {
            $this->error("Aucun formulaire avec le slug « {$slug} ».");

            return self::FAILURE;
        }

        /** @var string|null $output */
        $output = $this->option('output');
        $outputPath = $output ?? (getcwd() ?: '.')."/{$slug}.json";

        File::put($outputPath, $action($form));

        $this->info("Formulaire [{$slug}] exporté vers {$outputPath}.");

        return self::SUCCESS;
    }
}
