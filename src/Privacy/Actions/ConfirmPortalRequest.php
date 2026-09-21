<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Audit\AuditLogger;
use Baobab\Mail\Mailer;
use Baobab\Privacy\Exceptions\ErasureAlreadyScheduledException;
use Baobab\Privacy\Exceptions\InvalidPortalLinkException;
use Baobab\Privacy\Exceptions\PortalRequestRefusedException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use JsonException;

/**
 * Seconde étape du portail public (spec 16 §4) : la personne a confirmé sur
 * la page ouverte par le lien. La demande entre alors dans le même pipeline
 * que celles créées en admin (`CreatePrivacyRequest`, origine `portal`).
 *
 * Le lien ne sert qu'une fois : un verrou de cache, posé atomiquement pour la
 * durée de vie du lien, refuse toute seconde confirmation. La signature est
 * vérifiée par l'appelant (adaptateur HTTP), le jeton ici : déchiffrement et
 * forme du contenu, jamais de confiance envers un jeton lisible mais mal formé.
 *
 * Un **effacement** confirmé est planifié puis annoncé par mail, avec son
 * lien d'annulation (décision 17). Un **refus** — dernier super-administrateur,
 * effacement déjà planifié — est dit à la personne, par la page et par mail
 * (décision 18) : elle a prouvé qu'elle possède la boîte, l'oracle d'existence
 * que garde la saisie n'a plus lieu d'être ici. Il est aussi audité.
 */
final class ConfirmPortalRequest
{
    public function __construct(
        private readonly CreatePrivacyRequest $create,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
    ) {}

    /**
     * Le type porté par le lien, sans le consommer : la page de confirmation
     * en a besoin pour dire ce que la personne s'apprête à confirmer.
     *
     * @throws InvalidPortalLinkException
     */
    public function typeOf(string $token): PrivacyRequestType
    {
        return $this->open($token)[1];
    }

    /**
     * @throws InvalidPortalLinkException
     * @throws PortalRequestRefusedException
     */
    public function __invoke(string $token): PrivacyRequest
    {
        [$email, $type] = $this->open($token);

        $minutes = max(1, (int) config('baobab.privacy.portal_link_minutes', 30));

        if (! Cache::add('privacy-portal-confirmed:'.hash('sha256', $token), true, now()->addMinutes($minutes + 1))) {
            throw InvalidPortalLinkException::used();
        }

        try {
            $request = ($this->create)(Subject::forEmail($email), null, 'portal', $type);
        } catch (AdminLockoutException|ErasureAlreadyScheduledException $e) {
            throw $this->refuse($email, $type, $e instanceof AdminLockoutException
                ? PortalRequestRefusedException::lastSuperAdmin()
                : PortalRequestRefusedException::alreadyScheduled());
        }

        $this->audit->record('privacy.portal.confirmed', $request, [
            'reference' => Pseudonym::of($email),
            'type' => $type->value,
        ]);

        if ($type === PrivacyRequestType::Erasure) {
            $this->announceErasure($request, $email);
        }

        return $request;
    }

    private function announceErasure(PrivacyRequest $request, string $email): void
    {
        $this->mailer->send('core.privacy_erasure_scheduled', $email, [
            'scheduled_for' => $request->scheduled_for?->format('d/m/Y H:i') ?? '',
            'cancel_url' => URL::signedRoute('baobab.privacy.portal.cancel', ['privacyRequest' => $request->uuid]),
        ]);
    }

    private function refuse(string $email, PrivacyRequestType $type, PortalRequestRefusedException $refusal): PortalRequestRefusedException
    {
        $this->audit->record('privacy.portal.refused', null, [
            'reference' => Pseudonym::of($email),
            'type' => $type->value,
            'reason' => $refusal->reason,
        ]);

        $this->mailer->send('core.privacy_erasure_refused', $email, ['reason' => $refusal->getMessage()]);

        return $refusal;
    }

    /**
     * @return array{0: string, 1: PrivacyRequestType}
     */
    private function open(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw InvalidPortalLinkException::invalid();
        }

        $email = is_array($payload) ? ($payload['email'] ?? null) : null;
        $type = is_array($payload) && is_string($payload['type'] ?? null) ? PrivacyRequestType::tryFrom($payload['type']) : null;

        if (! is_string($email) || $email === '' || $type === null) {
            throw InvalidPortalLinkException::invalid();
        }

        return [$email, $type];
    }
}
