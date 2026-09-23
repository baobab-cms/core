<?php

declare(strict_types=1);

namespace Baobab\Privacy\Cookies;

/**
 * Un cookie ou traceur déclaré (spec 16 §3.2) — par le Core (`CoreCookies`)
 * ou par un module actif (section `privacy.cookies` de son manifeste).
 * `source` est `core` ou le nom du module déclarant : l'écran de
 * consultation (Pass F2) dira qui dépose quoi.
 */
final readonly class CookieDeclaration
{
    public function __construct(
        public string $name,
        public CookieCategory $category,
        public string $purpose,
        public string $duration,
        public ?string $provider,
        public string $source,
    ) {}

    /**
     * @param  array{name: string, category: string, purpose: string, duration: string, provider?: string}  $entry
     */
    public static function fromManifest(array $entry, string $source): self
    {
        return new self(
            name: $entry['name'],
            category: CookieCategory::from($entry['category']),
            purpose: $entry['purpose'],
            duration: $entry['duration'],
            provider: $entry['provider'] ?? null,
            source: $source,
        );
    }
}
