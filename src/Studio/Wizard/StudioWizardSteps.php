<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

/**
 * Registre des étapes du Wizard Studio (spec-modules §5.2), patron exact
 * `FieldRegistry`/`WidgetRegistry` : Core enregistre ses handlers au boot
 * (`BaobabServiceProvider::registerCoreStudioSteps()`), chaque passe B1→B4
 * y ajoute la sienne sans toucher au shell (`StudioController`,
 * `<x-baobab::wizard>`). `labels()` fixe les 9 libellés du parcours
 * (spec-modules §5.2, `resources/lang/fr/admin.php#studio.steps`) pour que le
 * nav puisse orienter l'utilisateur même au-delà de la dernière étape
 * implémentée.
 */
final class StudioWizardSteps
{
    /** Clés de traduction `baobab::admin.studio.steps.*`, dans l'ordre du parcours. */
    private const LABEL_KEYS = [
        1 => 'identity',
        2 => 'models',
        3 => 'permissions',
        4 => 'routes',
        5 => 'policies',
        6 => 'menus',
        7 => 'widgets',
        8 => 'hooks',
        9 => 'recap',
    ];

    /** @var array<int, class-string<StudioStepHandler>> */
    private array $handlers = [];

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return array_map(
            static fn (string $key): string => __("baobab::admin.studio.steps.{$key}"),
            self::LABEL_KEYS
        );
    }

    /**
     * @param  class-string<StudioStepHandler>  $handlerClass
     */
    public function register(string $handlerClass): void
    {
        $this->handlers[app($handlerClass)->number()] = $handlerClass;
    }

    public function has(int $number): bool
    {
        return isset($this->handlers[$number]);
    }

    public function find(int $number): ?StudioStepHandler
    {
        if (! $this->has($number)) {
            return null;
        }

        return app($this->handlers[$number]);
    }

    /**
     * Numéro de la dernière étape implémentée (0 si aucune) — plafond de
     * progression pour `SaveStudioWizardStep`.
     */
    public function lastImplemented(): int
    {
        return $this->handlers === [] ? 0 : max(array_keys($this->handlers));
    }

    /**
     * @return list<StudioStepHandler>
     */
    public function all(): array
    {
        ksort($this->handlers);

        return array_map(static fn (string $class): StudioStepHandler => app($class), array_values($this->handlers));
    }
}
