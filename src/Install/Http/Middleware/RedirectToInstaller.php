<?php

declare(strict_types=1);

namespace Baobab\Install\Http\Middleware;

use Baobab\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Un site qui ne peut rien servir mène à l'installateur.
 *
 * **Étendu à l'administration et aux écrans invités le 3 septembre 2026**
 * (recette, suivi n° 242). Livré en C3a sur la seule racine `/`, il laissait
 * `/admin` partir vers `/login`, qui rend le layout invité, qui lit la base :
 * une archive décompressée répondait par une exception de base de données là
 * où l'installateur attendait à un clic. Or `/admin` est justement l'adresse
 * que l'écran final donne à l'utilisateur — celle qu'il rouvre le lendemain.
 * La garde, elle, n'a pas bougé d'un pouce.
 *
 * **Le besoin** : quelqu'un qui vient de décompresser l'archive ouvre son
 * domaine, pas `/install` — il ne sait pas encore que cette adresse existe.
 * Sans ce détour, il tombe sur le rendu public d'un site sans base, et rien ne
 * lui dit quoi faire. Demandé en recette le 2 septembre 2026 ; le n° 213 avait
 * déplacé « les routes conditionnelles » vers la Pass C, faute de destination
 * à l'époque.
 *
 * **Deux conditions, et la seconde n'était pas dans la demande.** L'absence de
 * lock ne suffit pas : le lock est la définition d'« installé » (§3), mais un
 * site monté à la main — `composer require baobab/core` dans une application
 * Laravel existante, ou un banc d'essai — n'en a jamais eu et fonctionne
 * parfaitement. La première version de ce détour ne regardait que le lock : elle
 * a détourné la page d'accueil de **seize** tests de rendu public et de SEO, ce
 * qui est la répétition, en petit, de ce qu'elle aurait fait à ces sites-là.
 *
 * On ne détourne donc que ce qui **ne peut rien servir** : pas de lock, *et*
 * une base hors d'atteinte. Un site qui a ses tables rend sa page d'accueil,
 * lock ou pas — s'il veut l'installateur, il connaît son adresse.
 *
 * La garde de base reprend le patron de `bootstrapActiveModules()` : la
 * question posée est « puis-je lire quelque chose », et une base injoignable
 * répond non sans lever.
 */
final class RedirectToInstaller
{
    public function __construct(private readonly InstallationState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled() || $this->databaseIsReady()) {
            return $next($request);
        }

        /*
         * **302 et jamais 301.** Le détour n'est vrai que le temps d'une
         * installation. Un 301 serait retenu par le navigateur *pour toujours*
         * : le site installé renverrait ses visiteurs vers un installateur
         * disparu, et son propriétaire n'aurait aucun moyen de défaire ce que
         * leurs navigateurs ont mémorisé.
         */
        return redirect()->route('baobab.install.gate');
    }

    private function databaseIsReady(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }
}
