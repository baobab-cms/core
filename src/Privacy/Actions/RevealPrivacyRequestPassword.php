<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Remet le mot de passe d'une archive d'export **une seule fois** (spec 16
 * §4, décision 10) : la lecture l'efface de la ligne, sous verrou, pour que
 * deux clics simultanés ne le révèlent pas deux fois. Renvoie `null` quand il
 * n'y en a plus (déjà lu, archive échue, demande sans archive). L'accès est
 * audité — jamais avec le mot de passe lui-même.
 */
final class RevealPrivacyRequestPassword
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(PrivacyRequest $request, ?User $actor = null): ?string
    {
        $password = DB::transaction(function () use ($request): ?string {
            $locked = PrivacyRequest::query()->lockForUpdate()->find($request->id);

            if ($locked === null || ! $locked->hasPendingPassword()) {
                return null;
            }

            $password = $locked->password;
            $locked->update(['password' => null]);

            return $password;
        });

        if ($password !== null) {
            $this->audit->record('privacy.export.password_revealed', $request, ['actor' => $actor?->getKey()]);
        }

        return $password;
    }
}
