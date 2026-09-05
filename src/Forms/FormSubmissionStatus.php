<?php

declare(strict_types=1);

namespace Baobab\Forms;

/**
 * Les quatre états d'une soumission (spec 14 §6.1). Catalogue fermé, patron
 * `Baobab\Mail\MailLogStatus`. `label()`/`badgeVariant()` ajoutés en Pass B5
 * avec l'écran qui les consomme (même leçon que `MailLogStatus`).
 */
enum FormSubmissionStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Spam = 'spam';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => __('baobab::admin.form_submissions.status_new'),
            self::Read => __('baobab::admin.form_submissions.status_read'),
            self::Spam => __('baobab::admin.form_submissions.status_spam'),
            self::Archived => __('baobab::admin.form_submissions.status_archived'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Read => 'neutral',
            self::Spam => 'danger',
            self::Archived => 'neutral',
        };
    }
}
