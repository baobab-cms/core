<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * État grossier d'une demande (spec 16 §4, décision 10) — pas de progression
 * fine. `Scheduled`/`Cancelled` ne concernent que l'effacement sous délai de
 * grâce (§4.3, décision 12) : `scheduled` attend son échéance, `pending` est
 * la demande prise en charge et mise en file.
 */
enum PrivacyRequestStatus: string
{
    case Scheduled = 'scheduled';
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Running => 'info',
            self::Failed => 'danger',
            self::Scheduled => 'warning',
            self::Expired, self::Pending, self::Cancelled => 'neutral',
        };
    }

    public function label(): string
    {
        return __('baobab::admin.privacy_requests.status_'.$this->value);
    }
}
