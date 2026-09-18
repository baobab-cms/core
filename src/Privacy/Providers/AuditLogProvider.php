<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Audit\Models\AuditEntry;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * `core.audit_log` (spec 16 §2.1, §5) : le journal d'audit — acteur,
 * usurpateur, ou compte objet d'une entrée. Un sujet sans compte n'y figure
 * jamais (l'audit ne stocke pas d'e-mail en clair hors `data`).
 */
final class AuditLogProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.audit_log';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.audit_log.title'),
            nature: __('baobab::privacy.audit_log.nature'),
            purpose: __('baobab::privacy.audit_log.purpose'),
            legalBasis: __('baobab::privacy.audit_log.legal_basis'),
            retention: __('baobab::privacy.audit_log.retention', ['days' => (int) config('baobab.audit.retention_days', 365)]),
        );
    }

    public function locate(Subject $subject): bool
    {
        $userId = $this->userIdOf($subject);

        if ($userId === null) {
            return false;
        }

        return AuditEntry::query()
            ->where(function (Builder $query) use ($userId): void {
                $query->where('actor_id', $userId)
                    ->orWhere('impersonator_id', $userId)
                    ->orWhere(function (Builder $query) use ($userId): void {
                        $query->where('auditable_type', (new User)->getMorphClass())
                            ->where('auditable_id', $userId);
                    });
            })
            ->exists();
    }
}
