<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\Mailer;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Support\Pseudonym;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Première étape du portail public (spec 16 §4, décisions 14 à 16) : envoie
 * le lien de vérification à l'adresse saisie. **Toujours**, quelle que soit
 * l'adresse et sans interroger les fournisseurs — c'est ce qui garde la
 * réponse et le délai identiques pour une adresse connue ou inconnue (pas
 * d'oracle d'existence). Le message ne contient rien de personnel.
 *
 * Le lien est signé, temporaire, et son sujet est un jeton **chiffré** :
 * l'adresse n'apparaît jamais en clair dans une URL (journaux d'accès,
 * historiques). C'est la possession de la boîte qui identifie la personne.
 */
final class RequestPortalVerification
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(string $email, PrivacyRequestType $type = PrivacyRequestType::Export): void
    {
        $email = mb_strtolower(trim($email));
        $minutes = max(1, (int) config('baobab.privacy.portal_link_minutes', 30));

        $token = Crypt::encryptString(json_encode(['email' => $email, 'type' => $type->value], JSON_THROW_ON_ERROR));

        $this->mailer->send('core.privacy_verification', $email, [
            'verify_url' => URL::temporarySignedRoute('baobab.privacy.portal.verify', now()->addMinutes($minutes), ['token' => $token]),
            'expires_in' => $minutes,
            'request_kind' => __('baobab::privacy.portal.kind_'.$type->value),
        ]);

        $this->audit->record('privacy.portal.requested', null, [
            'reference' => Pseudonym::of($email),
            'type' => $type->value,
        ]);
    }
}
