<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Audit\AuditLogger;
use Baobab\Forms\Models\Form;
use Illuminate\Console\Command;

/**
 * `baobab:forms:purge` (spec 14 §6.4, §10) — rétention **par formulaire**,
 * contrairement au patron `seo:purge-404-log`/`baobab:mail:purge-log` qui
 * lisent une seule valeur de config : chaque formulaire porte la sienne
 * (`forms.retention_days`), d'où l'itération plutôt qu'une requête unique.
 * Purge auditée (spec 14 §6.4 — l'IP tombe sous la même rétention, portée
 * par la même ligne, rien à purger séparément).
 *
 * Les fichiers joints (spec 14 §5, disque privé) ne sont pas encore purgés
 * ici : leur stockage n'existe pas avant la Pass C (rendu front). À
 * raccorder à ce moment — « aucun orphelin » de la spec vaut aussi pour
 * cette commande.
 */
final class FormSubmissionsPurgeCommand extends Command
{
    protected $signature = 'baobab:forms:purge';

    protected $description = 'Purge les soumissions de formulaires au-delà de la rétention propre à chaque formulaire.';

    public function __construct(private readonly AuditLogger $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $totalPurged = 0;

        Form::query()->each(function (Form $form) use (&$totalPurged): void {
            $purged = $form->submissions()
                ->where('created_at', '<=', now()->subDays($form->retention_days))
                ->delete();

            if ($purged > 0) {
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
