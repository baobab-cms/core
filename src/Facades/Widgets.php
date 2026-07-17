<?php

declare(strict_types=1);

namespace Baobab\Facades;

use Baobab\Widgets\WidgetsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool zoneHasContent(string $zoneKey)
 *
 * @see WidgetsManager
 */
final class Widgets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WidgetsManager::class;
    }
}
