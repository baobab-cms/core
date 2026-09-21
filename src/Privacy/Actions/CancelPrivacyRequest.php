<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Exceptions\RequestNotCancellableException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;

/**
 * Annule une demande d'effacement pendant son délai de grâce (spec 16 §4.3,
 * décision 13). L'annulation est atomique : `DispatchDueErasures` prend la
 * demande par la même condition `status = scheduled`, un seul des deux gagne.
 * La ligne reste, comme trace de la demande et de son annulation ; l'auteur
 * de l'annulation est celui que l'audit résout (utilisateur connecté).
 */
final class CancelPrivacyRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws RequestNotCancellableException hors effacement `scheduled`
     */
    public function __invoke(PrivacyRequest $request): PrivacyRequest
    {
        $updated = PrivacyRequest::query()
            ->whereKey($request->getKey())
            ->where('type', PrivacyRequestType::Erasure)
            ->where('status', PrivacyRequestStatus::Scheduled)
            ->update(['status' => PrivacyRequestStatus::Cancelled, 'finished_at' => now()]);

        if ($updated === 0) {
            throw RequestNotCancellableException::forRequest();
        }

        $request->refresh();

        $this->audit->record('privacy.request.cancelled', $request, ['type' => $request->type->value]);

        return $request;
    }
}
