<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Mail\Models\MailLogEntry;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;

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
}
