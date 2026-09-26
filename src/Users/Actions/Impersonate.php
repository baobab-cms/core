<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Facades\Hook;
use Baobab\Users\Exceptions\ImpersonationException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

/**
 * Spec 04 §9.1 : mécanique de session native (guard + session Laravel),
 * pas de nouvelle table — l'identité réelle vit en session le temps de
 * l'impersonation.
 */
final class Impersonate
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User $actor, User $target): void
    {
        if (Session::has('baobab.impersonator_id')) {
            throw new ImpersonationException('Cannot start an impersonation while already impersonating.');
        }

        // Un compte désactivé n'a aucun accès (spec 05 §5, décision 5 k) :
        // l'usurper contournerait le blocage, on le réactive d'abord.
        if ($target->isDeactivated()) {
            throw new ImpersonationException('Cannot impersonate a deactivated account.');
        }

        $this->manager->assertOutranks($actor, $target->level());

        Hook::action('baobab.user.impersonation.started', $actor, $target);

        Session::put('baobab.impersonator_id', $actor->getKey());
        Session::put(
            'baobab.impersonation_expires_at',
            now()->addMinutes((int) Config::get('baobab.impersonation.duration_minutes', 60))->toIso8601String(),
        );

        Auth::guard('baobab')->login($target);
        Session::regenerate();
    }
}
