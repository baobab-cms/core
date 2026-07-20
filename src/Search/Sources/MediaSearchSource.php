<?php

declare(strict_types=1);

namespace Baobab\Search\Sources;

use Baobab\Media\Models\Media;
use Baobab\Search\Contracts\SearchSource;
use Baobab\Search\SearchResultItem;
use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;

/**
 * Source Core « médias » (spec 11 §3.2) — contexte `admin` uniquement.
 * Requête directe (`LIKE`), pas de Scout : mêmes raisons que
 * `UsersSearchSource`. Gate `viewAny` sur `Media::class`, patron exact
 * `MediaController::index()` (`Baobab\Admin\Media\Http\Controllers\MediaController`).
 */
final class MediaSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'core.media';
    }

    public function label(): string
    {
        return 'Médias';
    }

    /**
     * @return list<'admin'|'front'>
     */
    public function contexts(): array
    {
        return ['admin'];
    }

    public function query(string $term, ?User $actor): SearchResults
    {
        if (! $actor?->can('viewAny', Media::class)) {
            return new SearchResults([]);
        }

        $media = Media::query()
            ->where(fn ($query) => $query->where('file_name', 'like', "%{$term}%")->orWhere('alt', 'like', "%{$term}%"))
            ->limit(10)
            ->get();

        return new SearchResults(array_values($media->map(fn (Media $item): SearchResultItem => new SearchResultItem(
            title: $item->file_name,
            url: route('admin.media.show', ['media' => $item->id]),
            excerpt: $item->alt,
        ))->all()));
    }
}
