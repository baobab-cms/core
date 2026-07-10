<?php

declare(strict_types=1);

namespace Baobab\Facades;

use Baobab\Hooks\HookRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void listen(string $name, callable|string $listener, int $priority = 10)
 * @method static void action(string $name, mixed ...$payload)
 * @method static array<string, list<array{priority: int, listener: string}>> actions()
 * @method static void modify(string $name, callable|string $listener, int $priority = 10)
 * @method static mixed filter(string $name, mixed $value, mixed ...$context)
 * @method static array<string, list<array{priority: int, listener: string}>> filters()
 *
 * @see HookRegistry
 */
final class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookRegistry::class;
    }
}
