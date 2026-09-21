<?php

declare(strict_types=1);

namespace Baobab\Privacy\Http\Controllers;

use Baobab\Forms\Support\FormSpamGuard;
use Baobab\Privacy\Actions\ConfirmPortalRequest;
use Baobab\Privacy\Actions\RequestPortalVerification;
use Baobab\Privacy\Actions\RevealPrivacyRequestPassword;
use Baobab\Privacy\Exceptions\InvalidPortalLinkException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Rendering\Actions\RenderPrivacyPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Portail public d'exercice des droits (spec 16 §4, Pass E1) : adaptateur
 * mince. Saisie de l'adresse, confirmation par lien signé, remise de
 * l'archive. Aucune autorisation — c'est la possession de la boîte e-mail qui
 * identifie la personne — mais toute page à lien vérifie sa signature ici et
 * répond par un état lisible plutôt que la page 403 brute.
 *
 * Deux gestes ne se font jamais sur un simple GET, parce que les analyseurs
 * de liens des messageries préchargent les GET : la création de la demande
 * et la révélation du mot de passe passent par un POST.
 */
final class PortalController
{
    public function __construct(private readonly RenderPrivacyPortal $render) {}

    public function show(): Response
    {
        return ($this->render)(session('privacy_portal') === 'sent' ? 'sent' : 'form');
    }

    /**
     * Réponse identique que l'adresse soit connue ou non, et pour une saisie
     * suspecte (honeypot, piège temporel) : ni oracle d'existence, ni indice
     * donné à un robot qu'il a été repéré (spec 14 §7.2).
     */
    public function request(Request $request, RequestPortalVerification $verify): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);

        if (! FormSpamGuard::isTriggered($request->all())) {
            $verify($validated['email'], PrivacyRequestType::Export);
        }

        return redirect()->route('baobab.privacy.portal')->with('privacy_portal', 'sent');
    }

    public function verify(Request $request): Response
    {
        if (! $request->hasValidSignature()) {
            return ($this->render)('invalid', [], 403);
        }

        return ($this->render)('confirm', ['confirmUrl' => $request->fullUrl()]);
    }

    public function confirm(Request $request, ConfirmPortalRequest $confirm): Response
    {
        if (! $request->hasValidSignature()) {
            return ($this->render)('invalid', [], 403);
        }

        try {
            $confirm((string) $request->query('token'));
        } catch (InvalidPortalLinkException $e) {
            return ($this->render)($e->reason, [], $e->reason === InvalidPortalLinkException::USED ? 410 : 403);
        }

        return ($this->render)('confirmed');
    }

    public function delivery(Request $request, PrivacyRequest $privacyRequest): Response
    {
        return $this->deliver($request, $privacyRequest);
    }

    /** Le mot de passe se révèle au clic, jamais au chargement : lu une seule fois. */
    public function reveal(Request $request, PrivacyRequest $privacyRequest, RevealPrivacyRequestPassword $reveal): Response
    {
        return $this->deliver($request, $privacyRequest, $reveal);
    }

    private function deliver(Request $request, PrivacyRequest $privacyRequest, ?RevealPrivacyRequestPassword $reveal = null): Response
    {
        // Lien sans échéance de signature : celle de l'archive se lit sur la demande (410).
        if (! $request->hasValidSignature()) {
            return ($this->render)('invalid', [], 403);
        }

        abort_unless($privacyRequest->origin === 'portal' && $privacyRequest->type === PrivacyRequestType::Export, 404);

        if ($privacyRequest->status === PrivacyRequestStatus::Expired
            || ($privacyRequest->expires_at !== null && $privacyRequest->expires_at->isPast())) {
            return ($this->render)('gone', [], 410);
        }

        abort_unless($privacyRequest->isDownloadable(), 404);

        $password = $reveal === null ? null : $reveal($privacyRequest);

        return ($this->render)('delivery', [
            'downloadUrl' => URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $privacyRequest->uuid]),
            'revealUrl' => $request->fullUrl(),
            'password' => $password,
            'hasPassword' => $password === null && $privacyRequest->refresh()->hasPendingPassword(),
            'expiresAt' => $privacyRequest->expires_at?->format('d/m/Y H:i'),
        ]);
    }
}
