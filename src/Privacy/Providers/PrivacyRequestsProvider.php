<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * `core.privacy_requests` (spec 16 §2.1, décision 10) : la table des demandes
 * détient elle-même l'adresse du sujet, elle entre donc au registre, à
 * l'export et à l'effacement comme tout autre traitement.
 *
 * `locate` ignore les demandes `pending`/`running` : c'est la demande en cours
 * d'exécution, celle qui interroge justement les fournisseurs — sans cette
 * exclusion, elle se trouverait elle-même et un sujet sans aucune autre donnée
 * obtiendrait une archive au lieu de « aucune donnée ». `erase`, lui, prend
 * toutes les lignes.
 */
final class PrivacyRequestsProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.privacy_requests';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.privacy_requests.title'),
            nature: __('baobab::privacy.privacy_requests.nature'),
            purpose: __('baobab::privacy.privacy_requests.purpose'),
            legalBasis: __('baobab::privacy.privacy_requests.legal_basis'),
            retention: __('baobab::privacy.privacy_requests.retention', ['days' => (int) config('baobab.privacy.export_retention_days', 7)]),
            externalServices: $this->mailServices(),
        );
    }

    public function locate(Subject $subject): bool
    {
        return $this->requestsOf($subject)
            ->whereNotIn('status', [PrivacyRequestStatus::Pending, PrivacyRequestStatus::Running])
            ->exists();
    }

    /**
     * L'archive et le mot de passe partent ; l'adresse est pseudonymisée ; la
     * ligne reste, comme preuve qu'une demande a eu lieu, sans plus rien de
     * personnel que sa référence.
     */
    public function erase(Subject $subject): EraseReport
    {
        $count = 0;

        foreach ($this->requestsOf($subject)->lazyById(100) as $request) {
            if ($request->file_disk !== null && $request->file_path !== null) {
                Storage::disk($request->file_disk)->delete($request->file_path);
            }

            $request->update([
                'subject_email' => $request->subject_email === null ? null : Pseudonym::address($request->subject_email),
                'file_disk' => null,
                'file_path' => null,
                'password' => null,
                'error_message' => null,
            ]);
            $count++;
        }

        return new EraseReport(EraseOutcome::Anonymized, $count, __('baobab::privacy.erasure.privacy_requests_note'));
    }

    /** Ni le chemin de l'archive ni le mot de passe : ils n'ont de sens que sur ce serveur. */
    public function export(Subject $subject): PersonalDataExport
    {
        $requests = [];

        foreach ($this->requestsOf($subject)->whereNotIn('status', [PrivacyRequestStatus::Pending, PrivacyRequestStatus::Running])->orderBy('id')->get() as $request) {
            $requests[] = [
                'type' => $request->type->value,
                'status' => $request->status->value,
                'origin' => $request->origin,
                'requested_at' => $request->created_at->toIso8601String(),
                'expires_at' => $request->expires_at?->toIso8601String(),
            ];
        }

        return new PersonalDataExport(['requests' => $requests]);
    }

    /**
     * @return Builder<PrivacyRequest>
     */
    private function requestsOf(Subject $subject): Builder
    {
        $userId = $this->userIdOf($subject);
        $email = $this->emailOf($subject);

        return PrivacyRequest::query()->where(function (Builder $query) use ($userId, $email): void {
            $query->whereRaw('1 = 0');

            if ($userId !== null) {
                $query->orWhere('subject_user_id', $userId);
            }

            if ($email !== null) {
                $query->orWhereRaw('LOWER(subject_email) = ?', [$email]);
            }
        });
    }
}
