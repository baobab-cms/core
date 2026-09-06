<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Audit\AuditLogger;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormFileStorage;
use Illuminate\Console\Command;

/**
 * `baobab:forms:purge` (spec 14 §6.4, §10) — rétention **par formulaire**,
 * contrairement au patron `seo:purge-404-log`/`baobab:mail:purge-log` qui
 * lisent une seule valeur de config : chaque formulaire porte la sienne
 * (`forms.retention_days`), d'où l'itération plutôt qu'une requête unique.
 * Purge auditée (spec 14 §6.4 — l'IP tombe sous la même rétention, portée
 * par la même ligne, rien à purger séparément).
 *
 * Les pièces jointes (spec 14 §5, disque privé, Pass C3) sont effacées avant
 * chaque ligne, via `FormFileStorage::deleteForSubmission()` — même point
 * unique que `DeleteFormSubmission`. Passe par `->get()` plutôt que le
 * `->delete()` en masse d'avant la Pass C3 : un `delete()` en une requête ne
 * donne aucune prise pour toucher les fichiers ligne par ligne.
 */
final class FormSubmissionsPurgeCommand extends Command
{
    protected $signature = 'baobab:forms:purge';

    protected $description = 'Purge les soumissions de formulaires au-delà de la rétention propre à chaque formulaire.';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FormFileStorage $fileStorage,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $totalPurged = 0;

        Form::query()->each(function (Form $form) use (&$totalPurged): void {
            $submissions = $form->submissions()
                ->where('created_at', '<=', now()->subDays($form->retention_days))
                ->get();

            $submissions->each(function (FormSubmission $submission): void {
                $this->fileStorage->deleteForSubmission($submission);
            });

            $purged = $submissions->count();

            if ($purged > 0) {
                FormSubmission::query()->whereIn('id', $submissions->pluck('id'))->delete();

                $this->audit->record('form_submissions.purged', $form, [
                    'count' => $purged,
                    'retention_days' => $form->retention_days,
                ]);
            }

            $totalPurged += $purged;
        });

        $this->info("{$totalPurged} soumission(s) purgée(s).");

        return self::SUCCESS;
    }
}
