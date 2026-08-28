<?php

declare(strict_types=1);

namespace Baobab\Install\Http\Middleware;

use Baobab\Install\InstallSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde des écrans d'installation proprement dits — spec 15 §6.2.
 *
 * Deux refus distincts, et ils ne se disent pas de la même façon.
 *
 * **Jeton non présenté** → retour à la porte. Ce n'est pas une erreur : c'est
 * le parcours normal de quelqu'un qui arrive par une URL profonde, ou dont la
 * session a expiré. On le renvoie à `/install` sans le gronder.
 *
 * **Verrou perdu** → 409. Une autre session d'installation a pris la main
 * pendant que celle-ci dormait. Continuer serait laisser deux navigateurs
 * écrire dans le même `.env` et migrer la même base : le conflit doit être
 * annoncé, jamais absorbé en silence.
 */
final class EnsureInstallSession
{
    public function __construct(private readonly InstallSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->session->isAuthenticated()) {
            return redirect()->route('baobab.install.gate');
        }

        abort_unless($this->session->touch(), 409, 'Une autre session d\'installation a pris la main sur ce site.');

        return $next($request);
    }
}
