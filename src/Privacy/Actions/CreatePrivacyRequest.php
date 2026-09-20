<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

/**
 * Crée une demande d'export (spec 16 §4, décision 10) et la met en file sur
 * `baobab-low` : l'exécution est asynchrone, seule voie qui serve aussi le
 * portail public de la Pass E. Ne vérifie pas que le sujet détient des
 * données — une demande sans donnée aboutit à `failed` avec son motif, ce qui
 * garde la création indiscernable pour le portail (pas d'oracle d'existence).
 * L'effacement (Pass D2) s'ajoutera ici par son type.
 */
final class CreatePrivacyRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Subject $subject, ?User $requestedBy = null, string $origin = 'admin'): PrivacyRequest
    {
        $request = PrivacyRequest::create([
            'type' => PrivacyRequestType::Export,
            'status' => PrivacyRequestStatus::Pending,
            'subject_user_id' => $subject->userId,
            'subject_email' => $subject->email,
            'origin' => $origin,
            'requested_by' => $requestedBy?->getKey(),
        ]);

        // Le sujet n'est jamais recopié dans l'audit : la demande, référencée
        // par son identifiant, suffit à le retrouver tant qu'elle existe.
        $this->audit->record('privacy.request.created', $request, [
            'type' => $request->type->value,
            'origin' => $origin,
        ]);

        RunPersonalDataExportJob::dispatch($request->id)->onQueue('baobab-low');

        return $request;
    }
}
