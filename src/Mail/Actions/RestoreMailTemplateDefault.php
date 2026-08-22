<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\TemplateRegistry;

/**
 * Restaure le défaut du code d'un template personnalisé (spec 13 §3.2, bouton
 * « Restaurer le défaut »). Supprimer la ligne suffit — c'est tout l'intérêt
 * de n'avoir en base que les personnalisations : le défaut n'est jamais copié
 * quelque part d'où il pourrait dériver, donc restaurer ne peut pas restaurer
 * une version périmée.
 *
 * C'est aussi la porte de sortie que la spec §3.3 exige face à la validation
 * bloquante : un template qu'on n'arrive plus à enregistrer reste toujours
 * restaurable. La clé est vérifiée contre ce que le code déclare — restaurer
 * une clé inconnue lève, comme partout ailleurs dans le domaine.
 */
final class RestoreMailTemplateDefault
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(string $key): void
    {
        $this->templates->declaration($key);

        $override = MailTemplateOverride::query()->where('key', $key)->first();

        if ($override === null) {
            return;
        }

        $override->delete();

        $this->audit->record('mail.template_restored', null, ['key' => $key]);
    }
}
