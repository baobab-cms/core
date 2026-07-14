<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\Mailer;

/**
 * E-mail de test (spec 13 §2.1) — premier réflexe de diagnostic d'un
 * intégrateur, exposé par l'écran de configuration transport (M8) et par le
 * CLI `baobab:mail:test` dès ce point.
 */
final class SendTestMail
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(string $address, ?string $templateKey = null): void
    {
        $key = $templateKey ?? 'core.test';

        $this->mailer->send($key, $address, ['sent_at' => now()->format('d/m/Y H:i')]);

        $this->audit->record('mail.test_sent', null, ['recipient' => $address, 'template' => $key]);
    }
}
