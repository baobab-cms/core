<?php

declare(strict_types=1);

namespace Baobab\Privacy;

use Baobab\Privacy\Contracts\PersonalDataProvider;
use Baobab\Privacy\Exceptions\DuplicatePrivacyProviderException;

/**
 * Registre des fournisseurs de données personnelles (spec 16 §2.1), patron
 * `Baobab\Widgets\WidgetRegistry` : singleton, alimenté au boot par le Core
 * puis par les modules via `Privacy::register()`.
 */
final class PrivacyRegistry
{
    /** @var array<string, PersonalDataProvider> */
    private array $providers = [];

    public function register(PersonalDataProvider $provider): void
    {
        $key = $provider->key();

        if (isset($this->providers[$key])) {
            throw DuplicatePrivacyProviderException::forKey($key);
        }

        $this->providers[$key] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * @return array<string, PersonalDataProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
