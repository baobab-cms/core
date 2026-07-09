<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\DependencyResolver;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;

/**
 * Désactive un module actif (spec 01 §3). Refuse si un module actif en
 * dépend encore. Les données du module restent en base.
 */
final class DeactivateModule
{
    public function __construct(private readonly DependencyResolver $dependencies) {}

    public function __invoke(string $name): Module
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        $activeModules = Module::where('status', 'active')->get();
        $this->dependencies->assertDeactivatable($module, $activeModules);

        $module->update(['status' => 'inactive']);

        Hook::action('baobab.module.deactivated', $module);

        return $module;
    }
}
