<?php

declare(strict_types=1);

namespace Baobab\Widgets;

use Baobab\Widgets\Actions\ResolveWidgetZone;

/**
 * Service de lecture pour les thèmes (spec 10 §3.4 : « le thème peut tester
 * `Widgets::zoneHasContent('sidebar')` pour ne pas rendre son conteneur »).
 * Pas une Action — comme `HookRegistry`, un service consulté depuis les
 * vues, pas une opération d'écriture avec sa propre policy/audit.
 */
final class WidgetsManager
{
    public function __construct(private readonly ResolveWidgetZone $resolveWidgetZone) {}

    public function zoneHasContent(string $zoneKey): bool
    {
        return ($this->resolveWidgetZone)($zoneKey) !== [];
    }
}
