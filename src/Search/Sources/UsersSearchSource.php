<?php

declare(strict_types=1);

namespace Baobab\Search\Sources;

use Baobab\Search\Contracts\SearchSource;
use Baobab\Search\SearchResultItem;
use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;

/**
 * Source Core « utilisateurs » (spec 11 §3.2) — contexte `admin` uniquement,
 * jamais exposée au front (aucun compte visiteur public dans ce projet).
 * Requête directe (`LIKE`), pas de Scout : le volume typique d'utilisateurs
 * ne justifie pas un moteur d'indexation dédié à ce stade.
 *
 * Pas de fiche utilisateur dédiée à ce jour (suivi n° 5, M9 point 2) — les
 * résultats pointent vers la liste plutôt qu'une page inexistante.
 */
final class UsersSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'core.users';
    }

    public function label(): string
    {
        return 'Utilisateurs';
    }

    /**
     * @return list<'admin'|'front'>
     */
    public function contexts(): array
    {
        return ['admin'];
    }

    public function query(string $term, ?User $actor, string $context = 'admin', array $options = []): SearchResults
    {
        // Même permission que l'écran admin/users lui-même (routes/admin.php)
        // — aucune permission "voir la liste" séparée n'existe à ce jour,
        // l'écran est entièrement gouverné par le droit d'impersonation.
        if (! $actor?->can('baobab.users.impersonate')) {
            return new SearchResults([]);
        }

        $users = User::query()
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->limit(10)
            ->get();

        return new SearchResults(array_values($users->map(fn (User $user): SearchResultItem => new SearchResultItem(
            title: $user->name,
            url: route('admin.users.index'),
            excerpt: $user->email,
            sourceKey: $this->key(),
            sourceLabel: $this->label(),
        ))->all()));
    }
}
