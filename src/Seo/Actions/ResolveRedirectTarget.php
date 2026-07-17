<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Seo\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Résout un chemin public contre les redirections actives (spec 07 §4) —
 * une seule lecture de la table par cycle de cache (`Cache::rememberForever`,
 * invalidé à l'écriture par `Create`/`Update`/`DeleteRedirect`, jamais au
 * hit — spec : « une redirection ne coûte pas une requête SQL »).
 * Correspondance exacte d'abord, puis motifs jokers (`/ancien-blog/*`) dans
 * l'ordre — un seul `*` par motif, au-delà de l'exemple de la spec
 * elle-même serait disproportionné pour v1.
 */
final class ResolveRedirectTarget
{
    public const CACHE_KEY = 'baobab.redirects.active';

    /**
     * @return array{id: int, target: string, status_code: int}|null
     */
    public function __invoke(string $path): ?array
    {
        $entries = $this->activeRedirects();

        foreach ($entries as $entry) {
            if (! str_contains($entry['source'], '*') && $entry['source'] === $path) {
                return ['id' => $entry['id'], 'target' => $entry['target'], 'status_code' => $entry['status_code']];
            }
        }

        foreach ($entries as $entry) {
            if (! str_contains($entry['source'], '*')) {
                continue;
            }

            $captured = $this->matchPattern($entry['source'], $path);

            if ($captured === null) {
                continue;
            }

            $target = str_contains($entry['target'], '*')
                ? str_replace('*', $captured, $entry['target'])
                : $entry['target'];

            return ['id' => $entry['id'], 'target' => $target, 'status_code' => $entry['status_code']];
        }

        return null;
    }

    private function matchPattern(string $pattern, string $path): ?string
    {
        $regex = '#^'.str_replace('\*', '(.*)', preg_quote($pattern, '#')).'$#';

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        return $matches[1] ?? '';
    }

    /**
     * @return list<array{id: int, source: string, target: string, status_code: int}>
     */
    private function activeRedirects(): array
    {
        /** @var list<array{id: int, source: string, target: string, status_code: int}> $entries */
        $entries = Cache::rememberForever(self::CACHE_KEY, fn (): array => Redirect::query()
            ->where('is_active', true)
            ->get(['id', 'source', 'target', 'status_code'])
            ->map(fn (Redirect $redirect): array => [
                'id' => $redirect->id,
                'source' => $redirect->source,
                'target' => $redirect->target,
                'status_code' => $redirect->status_code,
            ])
            ->all());

        return $entries;
    }
}
