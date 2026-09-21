<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Exceptions\ErasureAlreadyScheduledException;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\ErasureGuard;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Crée une demande d'exercice des droits (spec 16 §4, décisions 10 et 12).
 *
 * Un **export** est mis en file sur `baobab-low` : l'exécution est asynchrone,
 * seule voie qui serve aussi le portail public de la Pass E. Il ne vérifie pas
 * que le sujet détient des données — une demande sans donnée aboutit à
 * `failed` avec son motif, ce qui garde la création indiscernable pour le
 * portail (pas d'oracle d'existence).
 *
 * Un **effacement** naît `scheduled`, avec son échéance à la fin du délai de
 * grâce (§4.3) : rien n'est mis en file, `DispatchDueErasures` s'en charge à
 * l'échéance. Il est refusé d'emblée pour le dernier super-administrateur et
 * quand une autre demande d'effacement du même sujet attend déjà.
 */
final class CreatePrivacyRequest
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ErasureGuard $guard,
    ) {}

    /**
     * @throws AdminLockoutException effacement du dernier super-administrateur
     * @throws ErasureAlreadyScheduledException effacement déjà planifié pour ce sujet
     */
    public function __invoke(
        Subject $subject,
        ?User $requestedBy = null,
        string $origin = 'admin',
        PrivacyRequestType $type = PrivacyRequestType::Export,
    ): PrivacyRequest {
        if ($type === PrivacyRequestType::Erasure) {
            $this->guard->assertErasable($subject);

            if ($this->hasScheduledErasure($subject)) {
                throw ErasureAlreadyScheduledException::forSubject();
            }
        }

        $request = PrivacyRequest::create([
            'type' => $type,
            'status' => $type === PrivacyRequestType::Erasure ? PrivacyRequestStatus::Scheduled : PrivacyRequestStatus::Pending,
            'subject_user_id' => $subject->userId,
            'subject_email' => $subject->email,
            'origin' => $origin,
            'requested_by' => $requestedBy?->getKey(),
            'scheduled_for' => $type === PrivacyRequestType::Erasure
                ? now()->addDays(max(0, (int) config('baobab.privacy.erasure_grace_days', 15)))
                : null,
        ]);

        // Le sujet n'est jamais recopié dans l'audit : la demande, référencée
        // par son identifiant, suffit à le retrouver tant qu'elle existe.
        $this->audit->record('privacy.request.created', $request, [
            'type' => $request->type->value,
            'origin' => $origin,
            'scheduled_for' => $request->scheduled_for?->toIso8601String(),
        ]);

        if ($type === PrivacyRequestType::Export) {
            RunPersonalDataExportJob::dispatch($request->id)->onQueue('baobab-low');
        }

        return $request;
    }

    private function hasScheduledErasure(Subject $subject): bool
    {
        return PrivacyRequest::query()
            ->where('type', PrivacyRequestType::Erasure)
            ->where('status', PrivacyRequestStatus::Scheduled)
            ->where(function (Builder $query) use ($subject): void {
                $query->whereRaw('1 = 0');

                if ($subject->userId !== null) {
                    $query->orWhere('subject_user_id', $subject->userId);
                }

                if ($subject->email !== null) {
                    $query->orWhereRaw('LOWER(subject_email) = ?', [$subject->email]);
                }
            })
            ->exists();
    }
}
