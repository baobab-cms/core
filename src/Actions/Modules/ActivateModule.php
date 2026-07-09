<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\DependencyResolver;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;

/**
 * Active un module installé (spec 01 §3) : ses dépendances doivent être
 * installées, actives et compatibles en version. Ne charge pas le provider
 * dans le processus courant — ça se fera à la prochaine requête, en lisant
 * le statut en base (BaobabServiceProvider::register()).
 */
final class ActivateModule
{
    public function __construct(private readonly DependencyResolver $dependencies) {}

    public function __invoke(string $name): Module
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        $activeModules = Module::where('status', 'active')->get();
        $this->dependencies->assertActivatable($module, $activeModules);

        $module->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        Hook::action('baobab.module.activated', $module);

        return $module;
    }
}
