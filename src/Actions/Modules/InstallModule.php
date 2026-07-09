<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\DependencyResolver;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Modules\ModuleManifest;
use Illuminate\Support\Facades\Artisan;

/**
 * Installe un module découvert sur disque (spec 01 §3) : valide le manifest,
 * vérifie la compatibilité cœur/PHP et l'absence de cycle de dépendances,
 * persiste la ligne modules, exécute ses migrations, enregistre permissions
 * et menus déclarés. N'active pas le module (voir ActivateModule).
 */
final class InstallModule
{
    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly DependencyResolver $dependencies,
    ) {}

    public function __invoke(string $name): Module
    {
        $discovered = $this->discovery->scan()->get($name);

        if ($discovered === null) {
            throw ModuleNotFoundException::named($name);
        }

        $manifest = $discovered->manifest;

        $this->dependencies->assertCoreCompatible($manifest);
        $this->dependencies->assertNoCycle($manifest->name(), $manifest->requiresModules(), Module::all());

        $module = Module::create([
            'name' => $manifest->name(),
            'title' => $manifest->title(),
            'type' => $manifest->type(),
            'version' => $manifest->version(),
            'provider' => $manifest->provider(),
            'source' => $discovered->source,
            'path' => $discovered->path,
            'manifest' => $manifest->toArray(),
            'status' => 'installed',
            'installed_at' => now(),
        ]);

        // Volontairement hors transaction : les migrations (DDL) provoquent
        // un commit implicite sur MySQL, ce qui romprait l'atomicité promise
        // par une transaction englobante.
        $this->runMigrations($discovered->path);

        $this->persistPermissions($module, $manifest);
        $this->persistMenuItems($module, $manifest->adminMenuItems());

        Hook::action('baobab.module.installed', $module);

        return $module;
    }

    private function runMigrations(string $path): void
    {
        $migrationsPath = $path.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function persistPermissions(Module $module, ModuleManifest $manifest): void
    {
        foreach ($manifest->permissions() as $permission) {
            ModulePermission::create([
                'module_id' => $module->id,
                'key' => $permission['key'],
                'label' => $permission['label'],
                'default_roles' => $permission['default_roles'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function persistMenuItems(Module $module, array $items, ?int $parentId = null): void
    {
        foreach ($items as $item) {
            $menuItem = ModuleMenuItem::create([
                'module_id' => $module->id,
                'parent_id' => $parentId,
                'label' => $item['label'],
                'icon' => $item['icon'] ?? null,
                'route' => $item['route'] ?? null,
                'permission' => $item['permission'] ?? null,
                'order' => $item['order'] ?? 0,
            ]);

            if (! empty($item['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = $item['children'];
                $this->persistMenuItems($module, $children, $menuItem->id);
            }
        }
    }
}
