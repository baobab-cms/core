<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\DependencyResolver;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;

/**
 * Active un module installé (spec 01 §3) : ses dépendances doivent être
 * installées, actives et compatibles en version. Ne charge pas le provider
 * dans le processus courant — ça se fera à la prochaine requête, en lisant
 * le statut en base (BaobabServiceProvider::register()).
 *
 * Câblage déclaratif : les hooks.listens du manifest sont enregistrés
 * immédiatement dans le HookRegistry (spec 01 §4).
 */
final class ActivateModule
{
    public function __construct(
        private readonly DependencyResolver $dependencies,
        private readonly HookRegistry $registry,
    ) {}

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

        foreach ($module->manifest['hooks']['listens'] ?? [] as $hook => $listener) {
            $this->registry->listen($hook, $listener);
        }

        Hook::action('baobab.module.activated', $module);

        return $module;
    }
}
