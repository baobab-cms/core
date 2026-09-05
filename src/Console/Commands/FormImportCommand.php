<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Forms\Actions\ImportForm;
use Baobab\Forms\Exceptions\InvalidFormBlueprintException;
use Baobab\Forms\Exceptions\InvalidFormExportException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `baobab:forms:import {fichier}` (spec 14 §10). Un slug déjà connu met à
 * jour le formulaire existant (`ImportForm`) — pas de confirmation
 * supplémentaire ici, c'est le comportement documenté du réimport.
 */
final class FormImportCommand extends Command
{
    protected $signature = 'baobab:forms:import {fichier}';

    protected $description = 'Importe un formulaire depuis un fichier JSON produit par baobab:forms:export.';

    public function handle(ImportForm $action): int
    {
        /** @var string $path */
        $path = $this->argument('fichier');

        if (! File::exists($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        try {
            $form = $action(File::get($path));
        } catch (InvalidFormExportException|InvalidFormBlueprintException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Formulaire [{$form->slug}] importé (version {$form->version}).");

        return self::SUCCESS;
    }
}
