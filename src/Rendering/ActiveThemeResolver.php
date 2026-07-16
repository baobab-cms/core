<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Baobab\Modules\Models\Module;

/**
 * Lecture minimale du thème actif (spec 03 §7) — le cycle de vie complet
 * (exclusivité imposée, activation atomique, hooks, préview) est le contrat
 * de thème (M6 point 2). Ici : le premier module `type=theme` avec
 * `status=active`, le système de modules générique (M1) gérant déjà
 * install/activate.
 */
final class ActiveThemeResolver
{
    public function current(): ?Module
    {
        return Module::where('type', 'theme')->where('status', 'active')->first();
    }
}
