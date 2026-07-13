<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use Baobab\Facades\Hook;
use Baobab\Media\Exceptions\ExternalMediaException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Résout une URL tierce (YouTube, Vimeo, Dailymotion) en payload oEmbed
 * (spec 06 §7.1) : titre, HTML du lecteur, vignette. Fournisseurs sur liste
 * blanche — endpoints connus interrogés en HTTPS, l'URL utilisateur n'est
 * jamais requêtée directement (seule sa vignette, servie par le fournisseur,
 * l'est ensuite par CreateExternalMedia). Un module peut ajouter son
 * fournisseur via le filtre `baobab.media.oembed.providers`.
 */
final class OEmbedResolver
{
    /**
     * @var array<string, array{hosts: list<string>, endpoint: string}>
     */
    private const PROVIDERS = [
        'youtube' => [
            'hosts' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
            'endpoint' => 'https://www.youtube.com/oembed',
        ],
        'vimeo' => [
            'hosts' => ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'],
            'endpoint' => 'https://vimeo.com/api/oembed.json',
        ],
        'dailymotion' => [
            'hosts' => ['dailymotion.com', 'www.dailymotion.com', 'dai.ly'],
            'endpoint' => 'https://www.dailymotion.com/services/oembed',
        ],
    ];

    /**
     * @return array{provider: string, type: string, title: string|null, html: string|null, thumbnail_url: string|null, width: int|null, height: int|null}
     */
    public function resolve(string $url): array
    {
        [$provider, $endpoint] = $this->providerFor($url);

        try {
            $response = Http::timeout(10)->get($endpoint, ['url' => $url, 'format' => 'json']);
        } catch (Throwable) {
            throw ExternalMediaException::resolutionFailed($url);
        }

        if (! $response->successful()) {
            throw ExternalMediaException::resolutionFailed($url);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $response->json();

        return [
            'provider' => $provider,
            'type' => is_string($payload['type'] ?? null) ? $payload['type'] : 'video',
            'title' => is_string($payload['title'] ?? null) ? $payload['title'] : null,
            'html' => is_string($payload['html'] ?? null) ? $payload['html'] : null,
            'thumbnail_url' => is_string($payload['thumbnail_url'] ?? null) ? $payload['thumbnail_url'] : null,
            'width' => is_numeric($payload['width'] ?? null) ? (int) $payload['width'] : null,
            'height' => is_numeric($payload['height'] ?? null) ? (int) $payload['height'] : null,
        ];
    }

    public function supports(string $url): bool
    {
        return $this->matchProvider($url) !== null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function providerFor(string $url): array
    {
        $match = $this->matchProvider($url);

        if ($match === null) {
            throw ExternalMediaException::unsupportedUrl($url);
        }

        return $match;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function matchProvider(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        /** @var array<string, array{hosts: list<string>, endpoint: string}> $providers */
        $providers = Hook::filter('baobab.media.oembed.providers', self::PROVIDERS);

        foreach ($providers as $name => $definition) {
            if (in_array($host, $definition['hosts'], true)) {
                return [$name, $definition['endpoint']];
            }
        }

        return null;
    }
}
