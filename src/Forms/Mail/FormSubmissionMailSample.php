<?php

declare(strict_types=1);

namespace Baobab\Forms\Mail;

use Baobab\Mail\Contracts\MailSampleProvider;

/**
 * Données d'exemple de `core.form_submission` pour l'aperçu en admin (spec 13
 * §3.4, contrat ouvert par le suivi n° 190) — `Baobab\Mail` ne connaît jamais
 * Forms, cette classe vit donc du côté du domaine qui la déclare, patron
 * `MarkerPreservingCleanHtml`/`registerFormEmbedRichTextResolver()` (n° 270).
 */
final class FormSubmissionMailSample implements MailSampleProvider
{
    public function sample(): array
    {
        return [
            'form_title' => 'Contact',
            'submitted_at' => now()->format('d/m/Y H:i'),
            'fields_summary' => "Nom : Awa Diallo\nE-mail : awa@example.com\nMessage : Bonjour, je souhaite un devis.",
        ];
    }
}
