<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\DeactivateModule;
use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Exceptions\NotAThemeException;
use Illuminate\Support\Facades\DB;

/**
 * Active un thème installé, en désactivant d'abord l'ancien thème actif s'il
 * y en a un (spec 03 §1 : « un seul thème actif à la fois », spec 03 §7 :
 * activation atomique). S'appuie sur les actions génériques du cycle de vie
 * des modules (M1, `ActivateModule`/`DeactivateModule`) plutôt que d'en
 * dupliquer la logique de dépendances.
 */
final class ActivateTheme
{
    public function __construct(
        private readonly ActivateModule $activate,
        private readonly DeactivateModule $deactivate,
        private readonly SyncThemeLocations $syncLocations,
    ) {}

    public function __invoke(string $name): Module
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        if ($module->type !== 'theme') {
            throw NotAThemeException::forModule($name);
        }

        return DB::transaction(function () use ($module, $name): Module {
            $previous = Module::where('type', 'theme')
                ->where('status', 'active')
                ->where('id', '!=', $module->id)
                ->first();

            if ($previous instanceof Module) {
                ($this->deactivate)($previous->name);
            }

            $activated = ($this->activate)($name);
            ($this->syncLocations)($activated);

            Hook::action('baobab.theme.activated', $previous, $activated);

            return $activated;
        });
    }
}
