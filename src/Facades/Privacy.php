<?php

declare(strict_types=1);

namespace Baobab\Facades;

use Baobab\Privacy\Contracts\PersonalDataProvider;
use Baobab\Privacy\PrivacyRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(PersonalDataProvider $provider)
 * @method static bool has(string $key)
 * @method static array<string, PersonalDataProvider> all()
 *
 * @see PrivacyRegistry
 */
final class Privacy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PrivacyRegistry::class;
    }
}
