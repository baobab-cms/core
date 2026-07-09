<?php

declare(strict_types=1);

namespace Baobab\Hooks;

/**
 * Registre central des hooks (spec 01 §4). Version actuelle : le volet
 * "actions" (fire-and-forget), suffisant pour que le cycle de vie des modules
 * émette baobab.module.installed/activated/deactivated/uninstalled. Le volet
 * "filtres", le câblage déclaratif depuis les manifests et hook:list arrivent
 * avec le jalon dédié au système de hooks (roadmap M1, point 4).
 */
final class HookRegistry
{
    /**
     * @var array<string, array<int, list<callable|string>>>
     */
    private array $actionListeners = [];

    public function listen(string $name, callable|string $listener, int $priority = 10): void
    {
        $this->actionListeners[$name][$priority][] = $listener;
        ksort($this->actionListeners[$name]);
    }

    public function action(string $name, mixed ...$payload): void
    {
        foreach ($this->actionListeners[$name] ?? [] as $listeners) {
            foreach ($listeners as $listener) {
                $this->call($listener, array_values($payload));
            }
        }
    }

    /**
     * @return array<string, list<array{priority: int, listener: string}>>
     */
    public function actions(): array
    {
        $result = [];

        foreach ($this->actionListeners as $name => $byPriority) {
            foreach ($byPriority as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $result[$name][] = [
                        'priority' => $priority,
                        'listener' => is_string($listener) ? $listener : 'Closure',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $payload
     */
    private function call(callable|string $listener, array $payload): void
    {
        if (is_string($listener)) {
            /** @var callable $listener */
            $listener = app($listener);
        }

        $listener(...$payload);
    }
}
