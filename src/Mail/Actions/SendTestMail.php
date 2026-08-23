<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\Mailer;

/**
 * E-mail de test (spec 13 §2.1, §3.4) — premier réflexe de diagnostic d'un
 * intégrateur, exposé par le CLI `baobab:mail:test` depuis le M5 et par
 * l'écran `admin/mails`, qui l'appelle sur le template en cours d'édition.
 *
 * `$data` porte les valeurs à substituer. Vide — le cas du CLI — seul
 * `sent_at` est fourni, ce dont `core.test` a besoin et rien de plus ;
 * l'écran, lui, passe les valeurs d'exemple du template courant. Les deux
 * clients partagent donc la même Action plutôt qu'un envoi « spécial admin ».
 */
final class SendTestMail
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(string $address, ?string $templateKey = null, array $data = []): void
    {
        $key = $templateKey ?? 'core.test';

        // L'ordre compte : ce que l'Action **sait** (l'heure réelle de l'envoi)
        // prime sur les valeurs d'exemple que l'appelant fournit pour le reste.
        // L'inverse a été livré puis corrigé en vérification navigateur — un
        // e-mail de test annonçant « envoyé le Date/heure d'envoi du test »
        // n'est pas un test, c'est un bug qui se donne en spectacle.
        $this->mailer->send($key, $address, ['sent_at' => now()->format('d/m/Y H:i')] + $data);

        $this->audit->record('mail.test_sent', null, ['recipient' => $address, 'template' => $key]);
    }
}
