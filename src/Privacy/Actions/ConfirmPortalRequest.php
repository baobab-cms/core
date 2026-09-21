<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Exceptions\InvalidPortalLinkException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
 */
final class ConfirmPortalRequest
{
    public function __construct(
        private readonly CreatePrivacyRequest $create,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws InvalidPortalLinkException
     */
    public function __invoke(string $token): PrivacyRequest
    {
        [$email, $type] = $this->open($token);

        $minutes = max(1, (int) config('baobab.privacy.portal_link_minutes', 30));

        if (! Cache::add('privacy-portal-confirmed:'.hash('sha256', $token), true, now()->addMinutes($minutes + 1))) {
            throw InvalidPortalLinkException::used();
        }

        $request = ($this->create)(Subject::forEmail($email), null, 'portal', $type);

        $this->audit->record('privacy.portal.confirmed', $request, [
            'reference' => Pseudonym::of($email),
            'type' => $type->value,
        ]);

        return $request;
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
