<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Support\Pseudonym;
use Baobab\Support\Logger;
use Throwable;

/**
 * Exécute une demande d'effacement arrivée à échéance (spec 16 §4.3,
 * décision 12) en appelant le moteur `ErasePersonalData`, puis consigne le
 * résultat sur la demande. Le rapport consolidé est déjà dans l'audit
 * (`privacy.erased`) ; la demande ne garde que son statut.
 *
 * La demande est `running` pendant l'effacement : `core.privacy_requests`
 * ignore alors la ligne qui l'exécute au `locate` (c'est la demande en cours,
 * pas une donnée du sujet) et ne l'anonymise donc pas — l'adresse qu'elle porte
 * est pseudonymisée ici, une fois l'effacement réussi. Les autres demandes du
 * sujet, elles, le sont par le fournisseur.
 *
 * Un échec ne fuit pas : les motifs lisibles (sujet sans donnée, dernier
 * super-administrateur) sont conservés, toute autre erreur est journalisée et
 * la demande porte un motif générique — un message d'exception peut citer des
 * données personnelles. Pas de nouvel essai automatique : l'effacement est
 * transactionnel, et une demande `failed` se recrée en connaissance de cause.
 */
final class ExecutePersonalDataErasure
{
    public function __construct(
        private readonly ErasePersonalData $erase,
        private readonly Logger $logger,
    ) {}

    public function __invoke(PrivacyRequest $request): bool
    {
        if ($request->type !== PrivacyRequestType::Erasure || $request->status !== PrivacyRequestStatus::Pending) {
            return false;
        }

        $request->update(['status' => PrivacyRequestStatus::Running, 'started_at' => now()]);

        // Capturé avant l'effacement : `erase` pseudonymise l'adresse portée par la demande.
        $subject = $request->subject();

        try {
            ($this->erase)($subject);
        } catch (NoPersonalDataException|AdminLockoutException $e) {
            $this->fail($request, $e->getMessage());

            return false;
        } catch (Throwable $e) {
            $this->logger->error("Échec de l'effacement de données personnelles.", ['request' => $request->uuid, 'exception' => $e]);
            $this->fail($request, __('baobab::privacy.erasure.failed'));

            return false;
        }

        $request->refresh()->update([
            'status' => PrivacyRequestStatus::Completed,
            'subject_email' => $request->subject_email === null || Pseudonym::isPseudonymized($request->subject_email) ? $request->subject_email : Pseudonym::address($request->subject_email),
            'finished_at' => now(),
        ]);

        return true;
    }

    private function fail(PrivacyRequest $request, string $message): void
    {
        $request->refresh()->update([
            'status' => PrivacyRequestStatus::Failed,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
