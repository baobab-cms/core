<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Mail\Models\MailLogEntry;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;

/** `core.mail_log` (spec 16 §2.1, spec 13 §4.3) : le journal des e-mails envoyés. */
final class MailLogProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.mail_log';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.mail_log.title'),
            nature: __('baobab::privacy.mail_log.nature'),
            purpose: __('baobab::privacy.mail_log.purpose'),
            legalBasis: __('baobab::privacy.mail_log.legal_basis'),
            retention: __('baobab::privacy.mail_log.retention', [
                'days' => (int) config('baobab.mail.log_retention_days', 90),
                'body_days' => (int) config('baobab.mail.log_body_retention_days', 7),
            ]),
            externalServices: $this->mailServices(),
        );
    }

    public function locate(Subject $subject): bool
    {
        $email = $this->emailOf($subject);

        return $email !== null
            && MailLogEntry::query()->whereRaw('LOWER(recipient) = ?', [$email])->exists();
    }

    /**
     * Le destinataire est haché (spec 13 §4.3), le corps et l'erreur vidés ;
     * l'objet du message reste, faute d'en connaître la part personnelle, et
     * le rapport le dit. La chronologie d'envoi (gabarit, statut, date) demeure.
     */
    public function erase(Subject $subject): EraseReport
    {
        $email = (string) $this->emailOf($subject);

        $count = MailLogEntry::query()
            ->whereRaw('LOWER(recipient) = ?', [$email])
            ->update(['recipient' => Pseudonym::address($email), 'body' => null, 'error' => null]);

        return new EraseReport(EraseOutcome::Anonymized, $count, __('baobab::privacy.erasure.mail_log_note'));
    }

    /** Le corps n'est plus là une fois purgé (rétention courte) : `null` alors. */
    public function export(Subject $subject): PersonalDataExport
    {
        $emails = [];

        foreach (MailLogEntry::query()->whereRaw('LOWER(recipient) = ?', [$this->emailOf($subject)])->orderBy('id')->get() as $entry) {
            $emails[] = [
                'template' => $entry->template_key,
                'subject' => $entry->subject,
                'status' => $entry->status,
                'sent_at' => $entry->sent_at?->toIso8601String(),
                'body' => $entry->body,
            ];
        }

        return new PersonalDataExport(['emails' => $emails]);
    }
}
