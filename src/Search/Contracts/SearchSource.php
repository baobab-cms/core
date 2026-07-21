<?php

declare(strict_types=1);

namespace Baobab\Search\Contracts;

use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;

/**
 * Une source de recherche (spec 11 §3.2) — un module (ou le Core, §3.2
 * dernier paragraphe) en enregistre une pour rendre cherchable une entité
 * qui n'est pas un Content Type (utilisateurs, médias, entités propres).
 * Contrairement à l'illustration de la spec (`Search::register(new class
 * implements SearchSource {...})`, une instance), `SearchRegistry` enregistre
 * une `class-string` résolue via le conteneur (`app($class)`) — patron exact
 * `FieldRegistry`/`WidgetRegistry`, écart validé avec l'utilisateur : permet
 * l'injection de dépendances dans `query()` (policies, Eloquent) sans
 * bricolage, cohérent avec le reste du code base.
 */
interface SearchSource
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<'admin'|'front'>
     */
    public function contexts(): array;

    /**
     * `$context` ajouté en Pass B (signature étendue avant tout consommateur
     * externe — Pass A venait d'être mergée, aucun module tiers n'implémente
     * encore ce contrat) : une même source produit des URLs différentes selon
     * le contexte (admin → écran d'édition, front → page publique), et le
     * front impose « contenus publiés seulement » indépendamment de l'acteur
     * (spec 11 §4.2). `$options` : filtres propres au consommateur (ex.
     * `type`/`filters` de `GET /api/v1/search`) — une source ignore
     * silencieusement les options qu'elle ne comprend pas.
     *
     * @param  array<string, mixed>  $options
     */
    public function query(string $term, ?User $actor, string $context = 'admin', array $options = []): SearchResults;
}
