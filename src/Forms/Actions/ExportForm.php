<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Forms\Models\Form;

/**
 * Exporte un formulaire en JSON (spec 14 §2.1, §10) — un format **autonome au
 * formulaire**, et non le format d'échange générique de la spec 12 §5, qui
 * n'existe pas encore (spec spécifiée, jamais implémentée). Écart assumé,
 * décidé à l'ouverture du point 6 (suivi n° 259) — patron déjà suivi pour
 * `field.relation` (n° 169) : le workflow agence staging → prod (§10) n'a pas
 * à attendre un mécanisme générique non construit.
 *
 * `source` (spec 14 §2.1) réserve la place pour un futur mode « formulaire
 * fourni par module » — toujours `admin` en v1, seule source qui existe.
 */
final class ExportForm
{
    public const FORMAT_VERSION = 1;

    public function __invoke(Form $form): string
    {
        return (string) json_encode([
            'source' => 'admin',
            'format_version' => self::FORMAT_VERSION,
            'slug' => $form->slug,
            'title' => $form->title,
            'fields' => $form->blueprint['fields'] ?? [],
            'settings' => $form->settings,
            'store_submissions' => $form->store_submissions,
            'retention_days' => $form->retention_days,
            'retain_ip' => $form->retain_ip,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
