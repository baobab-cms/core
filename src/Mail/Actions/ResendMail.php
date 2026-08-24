<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Exceptions\MailNotResendableException;
use Baobab\Mail\Mailer;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Mail\TemplateRegistry;

/**
 * Renvoi d'un e-mail depuis le journal (spec 13 §4.2) — **re-rendu depuis
 * l'état courant**, jamais réémission d'une copie figée.
 *
 * C'est la divergence assumée avec `RedeliverWebhookDelivery`, qui rejoue le
 * payload stocké au motif que la source a pu changer depuis. Un webhook parle
 * à une machine qui saura réconcilier ; un e-mail parle à quelqu'un, et lui
 * réexpédier un lien périmé ou un contenu devenu faux est pire que de ne rien
 * envoyer. D'où le passage obligé par un resolver, et le refus explicite quand
 * il n'y en a pas ou qu'il ne peut plus rien produire.
 *
 * L'envoi lui-même repasse par `Mailer::send()` : le renvoi produit donc sa
 * **propre** ligne de journal, en `queued`, au lieu de recycler celle qu'on
 * consultait. Deux tentatives sont deux faits, et l'écraser perdrait la trace
 * de l'échec qui a motivé le renvoi.
 */
final class ResendMail
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(MailLogEntry $entry): void
    {
        $declaration = $this->templates->declaration($entry->template_key);

        if ($declaration->resolver === null) {
            throw MailNotResendableException::noResolver($entry->template_key);
        }

        $resolver = app($declaration->resolver);

        if (! $resolver instanceof MailDataResolver) {
            throw MailNotResendableException::invalidResolver($entry->template_key, $declaration->resolver);
        }

        $data = $resolver->resolve($entry);

        if ($data === null) {
            throw MailNotResendableException::sourceGone($entry->template_key);
        }

        $this->mailer->send($entry->template_key, $entry->recipient, $data);

        $this->audit->record('mail.resent', null, [
            'template' => $entry->template_key,
            'recipient' => $entry->recipient,
            'mail_log_id' => $entry->id,
        ]);

        Hook::action('baobab.mail.resent', $entry);
    }
}
