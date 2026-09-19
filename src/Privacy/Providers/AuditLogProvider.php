<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Audit\Models\AuditEntry;
use Baobab\Facades\Hook;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
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

    /**
     * Pseudonymise, ne supprime pas (spec 16 §5) : la chronologie des actions
     * demeure (intérêt légitime, sécurité et preuve). `actor_id` désigne déjà
     * le compte anonymisé ; on efface l'IP et le user-agent des entrées dont le
     * sujet est l'auteur (acteur ou usurpateur — ceux d'une entrée où il n'est
     * que l'objet sont ceux d'un autre), et on retire de `data`, partout où
     * le sujet figure, les clés du filtre `baobab.privacy.audit_personal_keys`.
     *
     * Écrit par le builder : le modèle `AuditEntry` refuse toute écriture
     * individuelle (append-only), celle-ci en est l'unique exception.
     */
    public function erase(Subject $subject): EraseReport
    {
        $userId = (int) $this->userIdOf($subject);

        /** @var list<string> $keys */
        $keys = Hook::filter('baobab.privacy.audit_personal_keys', ['email', 'name', 'ip', 'ip_address', 'user_agent']);
        $count = 0;

        foreach ($this->entriesOf($userId)->lazyById(200) as $entry) {
            $changes = [];

            if ($entry->actor_id === $userId || $entry->impersonator_id === $userId) {
                $changes['ip_address'] = null;
                $changes['user_agent'] = null;
            }

            if ($entry->data !== null) {
                $changes['data'] = json_encode($this->scrub($entry->data, $keys), JSON_THROW_ON_ERROR);
            }

            AuditEntry::query()->whereKey($entry->getKey())->update($changes);
            $count++;
        }

        return new EraseReport(EraseOutcome::Anonymized, $count, __('baobab::privacy.erasure.audit_log_note'));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    private function scrub(array $data, array $keys): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value, $keys) : $value;
        }

        return $clean;
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
