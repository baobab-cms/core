<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Audit\Models\AuditEntry;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\PersonalDataExport;
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

        return $this->entriesOf($userId)->exists();
    }

    /** Chaque entrée porte le rôle du sujet : acteur, usurpateur ou objet. */
    public function export(Subject $subject): PersonalDataExport
    {
        $userId = (int) $this->userIdOf($subject);
        $entries = [];

        foreach ($this->entriesOf($userId)->orderBy('id')->get() as $entry) {
            $entries[] = [
                'action' => $entry->action,
                'role' => match (true) {
                    $entry->actor_id === $userId => 'actor',
                    $entry->impersonator_id === $userId => 'impersonator',
                    default => 'subject',
                },
                'object_type' => $entry->auditable_type,
                'object_id' => $entry->auditable_id,
                'data' => $entry->data,
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'at' => $entry->created_at->toIso8601String(),
            ];
        }

        return new PersonalDataExport(['entries' => $entries]);
    }

    /**
     * @return Builder<AuditEntry>
     */
    private function entriesOf(int $userId): Builder
    {
        return AuditEntry::query()
            ->where(function (Builder $query) use ($userId): void {
                $query->where('actor_id', $userId)
                    ->orWhere('impersonator_id', $userId)
                    ->orWhere(function (Builder $query) use ($userId): void {
                        $query->where('auditable_type', (new User)->getMorphClass())
                            ->where('auditable_id', $userId);
                    });
            });
    }
}
