<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

use Baobab\Facades\Hook;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Registre des profils de marque (spec 18 §7.2) : 8 presets Core sous
 * `resources/branding/profiles/{slug}.json`, chacun un sous-ensemble du
 * vocabulaire `DesignTokenSchema::GROUPS`. Extensible par le filtre
 * `baobab.branding.profiles` — un module ajoute directement une entrée
 * `slug => ['label' => ..., 'tokens' => [...]]`, sans fichier requis (futur
 * marketplace).
 */
final class BrandProfileRegistry
{
    /**
     * @var array<string, string>
     */
    private const array CORE_LABELS = [
        'corporate' => 'Corporate',
        'modern' => 'Moderne',
        'elegant' => 'Élégant',
        'luxury' => 'Luxe',
        'education' => 'Éducation',
        'medical' => 'Médical',
        'startup' => 'Startup',
        'minimal' => 'Minimal',
    ];

    /**
     * @return array<string, array{label: string, tokens: array<string, array<string, string>>}>
     */
    public function all(): array
    {
        $profiles = [];

        foreach (File::glob($this->directory().'/*.json') as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);

            $profiles[$slug] = [
                'label' => self::CORE_LABELS[$slug] ?? Str::headline($slug),
                'tokens' => $this->validated($this->decode($path)),
            ];
        }

        /** @var array<string, array{label: string, tokens: array<string, array<string, string>>}> $filtered */
        $filtered = Hook::filter('baobab.branding.profiles', $profiles);

        return $filtered;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function load(string $slug): array
    {
        return $this->all()[$slug]['tokens'] ?? [];
    }

    public function label(string $slug): ?string
    {
        return $this->all()[$slug]['label'] ?? null;
    }

    private function directory(): string
    {
        return dirname(__DIR__, 3).'/resources/branding/profiles';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Défensif vis-à-vis du filtre (une entrée externe pourrait injecter un
     * groupe/clé hors vocabulaire) — même garde que
     * `BrandingController::rejectUnknownTokens`, silencieuse ici (un profil
     * mal formé perd juste ses clés invalides plutôt que de faire échouer
     * tout l'écran).
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, array<string, string>>
     */
    private function validated(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $group => $values) {
            if (! array_key_exists($group, DesignTokenSchema::GROUPS) || ! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (in_array($key, DesignTokenSchema::GROUPS[$group], true) && is_string($value)) {
                    $result[$group][$key] = $value;
                }
            }
        }

        return $result;
    }
}
