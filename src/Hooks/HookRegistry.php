<?php

declare(strict_types=1);

namespace Baobab\Hooks;

/**
 * Registre central des hooks (spec 01 §4).
 *
 * Actions  : fire-and-forget, plusieurs listeners, payloads variadic.
 * Filtres  : chaîne de transformation — chaque listener reçoit la valeur
 *            courante + contexte optionnel et renvoie la valeur modifiée.
 * Priorité : entier, défaut 10, ordre croissant (comme WordPress).
 * Listeners: closure ou FQCN résolu via le conteneur IoC.
 */
final class HookRegistry
{
    /** @var array<string, array<int, list<callable|string>>> */
    private array $actionListeners = [];

    /** @var array<string, array<int, list<callable|string>>> */
    private array $filterListeners = [];

    // ── Actions ───────────────────────────────────────────────────────────────

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
        return $this->introspect($this->actionListeners);
    }

    // ── Filtres ───────────────────────────────────────────────────────────────

    public function modify(string $name, callable|string $listener, int $priority = 10): void
    {
        $this->filterListeners[$name][$priority][] = $listener;
        ksort($this->filterListeners[$name]);
    }

    public function filter(string $name, mixed $value, mixed ...$context): mixed
    {
        foreach ($this->filterListeners[$name] ?? [] as $listeners) {
            foreach ($listeners as $listener) {
                $value = $this->callFilter($listener, $value, array_values($context));
            }
        }

        return $value;
    }

    /**
     * @return array<string, list<array{priority: int, listener: string}>>
     */
    public function filters(): array
    {
        return $this->introspect($this->filterListeners);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    /**
     * @param  list<mixed>  $context
     */
    private function callFilter(callable|string $listener, mixed $value, array $context): mixed
    {
        if (is_string($listener)) {
            /** @var callable $listener */
            $listener = app($listener);
        }

        return $listener($value, ...$context);
    }

    /**
     * @param  array<string, array<int, list<callable|string>>>  $store
     * @return array<string, list<array{priority: int, listener: string}>>
     */
    private function introspect(array $store): array
    {
        $result = [];

        foreach ($store as $name => $byPriority) {
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
}
