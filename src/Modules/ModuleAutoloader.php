<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Modules\Models\Module;
use Composer\Autoload\ClassLoader;
use RuntimeException;

/**
 * Enregistre dynamiquement l'autoload PSR-4 des modules locaux actifs
 * (spec 01 §7.1 décision 1 : « le Core enregistre un chargeur »). Sans ça,
 * `class_exists($module->provider)` (BaobabServiceProvider::bootstrapActiveModules)
 * ne peut réussir que si la classe est déjà couverte par l'autoload du
 * package lui-même — ce qui n'est jamais le cas pour un module généré sous
 * /modules avec son propre namespace.
 */
final class ModuleAutoloader
{
    public function registerFor(Module $module): void
    {
        foreach ($module->manifest['autoload']['psr-4'] ?? [] as $namespace => $relativePath) {
            $this->classLoader()->addPsr4($namespace, $module->path.'/'.$relativePath);
        }
    }

    private function classLoader(): ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        if (! $loader instanceof ClassLoader) {
            throw new RuntimeException('No Composer class loader is registered in this process.');
        }

        return $loader;
    }
}
