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

    public function query(string $term, ?User $actor): SearchResults;
}
