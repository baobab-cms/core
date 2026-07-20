<?php

declare(strict_types=1);

namespace Baobab\Search;

use Baobab\Search\Contracts\SearchSource;
use Baobab\Search\Exceptions\UnknownSearchSourceException;

/**
 * Registre central des sources de recherche (spec 11 §3.2), patron exact
 * `Baobab\ContentTypes\Fields\FieldRegistry`/`Baobab\Widgets\WidgetRegistry` :
 * `class-string` résolue via le conteneur, jamais une instance pré-construite
 * (écart avec la lettre de la spec, validé avec l'utilisateur — voir
 * docblock `SearchSource`).
 */
final class SearchRegistry
{
    /** @var array<string, class-string<SearchSource>> */
    private array $sources = [];

    /**
     * @param  class-string<SearchSource>  $source
     */
    public function register(string $source): void
    {
        $this->sources[app($source)->key()] = $source;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function resolve(string $key): SearchSource
    {
        if (! $this->has($key)) {
            throw UnknownSearchSourceException::forKey($key);
        }

        return app($this->sources[$key]);
    }

    /**
     * @return array<string, class-string<SearchSource>>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
