<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/** État grossier d'une demande (spec 16 §4, décision 10) — pas de progression fine. */
enum PrivacyRequestStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Running => 'info',
            self::Failed => 'danger',
            self::Expired, self::Pending => 'neutral',
        };
    }

    public function label(): string
    {
        return __('baobab::admin.privacy_requests.status_'.$this->value);
    }
}
