<?php

declare(strict_types=1);

namespace Baobab\Install\Http\Middleware;

use Baobab\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/install/*` n'existe qu'en l'absence de lock — spec 15 §6.1 et §6.3.
 *
 * **Une seconde serrure, volontairement redondante.** Le provider n'enregistre
 * déjà pas ces routes quand le lock est là (§6.3 : « les routes `/install/*`
 * cessent d'exister au chargement suivant »). Ce middleware couvre l'intervalle
 * que cette phrase laisse ouvert : *au chargement suivant*. Le processus qui
 * vient d'écrire le lock a, lui, ses routes encore en mémoire — et une
 * configuration mise en cache (`route:cache`) peut prolonger l'écart bien
 * au-delà d'une requête.
 *
 * Le coût est d'une lecture de fichier ; le risque couvert est qu'une
 * installation terminée se laisse rejouer.
 *
 * **404 et non 403** : un site installé ne doit pas révéler qu'il a eu un
 * installateur, ni à quelle adresse. « Cette page n'existe pas » est la seule
 * réponse qui n'apprenne rien.
 */
final class EnsureNotInstalled
{
    public function __construct(private readonly InstallationState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->isInstalled(), 404);

        return $next($request);
    }
}
