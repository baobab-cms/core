<?php

declare(strict_types=1);

namespace Baobab\Mail;

/**
 * Sujet + corps par défaut d'un template résolu (spec 13 §3.1). Pas de lookup
 * de personnalisation admin ici — la table `mail_templates` (§3.2) est M8, ce
 * DTO ne porte que ce que le code du module a déclaré.
 */
final readonly class MailTemplate
{
    public function __construct(
        public string $key,
        public string $subject,
        public string $body,
    ) {}
}
