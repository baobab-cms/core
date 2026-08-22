<?php

declare(strict_types=1);

namespace Baobab\Mail;

/**
 * Sujet + corps **résolus** d'un template (spec 13 §3.1-3.2) : le défaut du
 * code, recouvert par la personnalisation admin quand il y en a une. C'est ce
 * que `Mailer` rend ; la déclaration du code, elle, est
 * `MailTemplateDeclaration`.
 *
 * `$fromAddress`/`$fromName` portent l'expéditeur par template (§3.2,
 * « optionnel, sinon global ») : `null` signifie « l'expéditeur global de
 * `config/mail.php` », jamais une valeur codée en dur ici.
 */
final readonly class MailTemplate
{
    public function __construct(
        public string $key,
        public string $subject,
        public string $body,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public bool $customised = false,
    ) {}
}
