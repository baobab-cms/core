<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Modules\Models\Module;
use Baobab\Themes\Models\ThemeMenuLocation;
use Baobab\Themes\Models\ThemeWidgetZone;
use Illuminate\Database\Eloquent\Model;

/**
 * Registre des emplacements de menus/zones de widgets déclarés par le thème
 * activé (spec 03 §7) — pose les clés, ne construit pas leur assignation
 * (point 4). Une clé que le thème précédent déclarait mais que le nouveau ne
 * déclare plus devient orpheline (`is_active = false`) plutôt que supprimée :
 * réactiver ce thème plus tard la repasse à `true` sans rien recréer.
 */
final class SyncThemeLocations
{
    public function __invoke(Module $theme): void
    {
        /** @var array<string, string> $menus */
        $menus = $theme->manifest['theme']['menus'] ?? [];
        /** @var array<string, string> $widgetZones */
        $widgetZones = $theme->manifest['theme']['widget_zones'] ?? [];

        $this->sync(ThemeMenuLocation::class, $menus);
        $this->sync(ThemeWidgetZone::class, $widgetZones);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $declared
     */
    private function sync(string $modelClass, array $declared): void
    {
        foreach ($declared as $key => $label) {
            $modelClass::query()->updateOrCreate(['key' => $key], ['label' => $label, 'is_active' => true]);
        }

        $modelClass::query()->whereNotIn('key', array_keys($declared))->update(['is_active' => false]);
    }
}
