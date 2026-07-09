<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Modules\Exceptions\IncompatibleModuleException;
use Baobab\Modules\Exceptions\ModuleDependencyCycleException;
use Baobab\Modules\Exceptions\ModuleHasActiveDependentsException;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Collection;

/**
 * Vérifications de compatibilité et de graphe de dépendances (spec 01 §3.1).
 */
final class DependencyResolver
{
    public function assertCoreCompatible(ModuleManifest $manifest): void
    {
        $cms = $manifest->requiresCms();

        if ($cms !== null && ! SemverConstraint::satisfiedBy($cms, (string) config('baobab.version'))) {
            throw IncompatibleModuleException::coreVersion($manifest->name(), $cms);
        }

        $php = $manifest->requiresPhp();

        if ($php !== null && ! SemverConstraint::satisfiedBy($php, PHP_VERSION)) {
            throw IncompatibleModuleException::phpVersion($manifest->name(), $php);
        }
    }

    /**
     * Détecte un cycle dans le graphe de dépendances (module → requires.modules),
     * en incluant le candidat à l'installation. Appelée à l'installation — la
     * simple présence des dépendances n'est pas requise ici (ça, c'est
     * assertActivatable), seule l'absence de cycle compte.
     *
     * @param  array<string, string>  $requiredModules  requires.modules du candidat
     * @param  Collection<int, Module>  $installedModules  modules déjà installés (hors candidat)
     */
    public function assertNoCycle(string $candidateName, array $requiredModules, Collection $installedModules): void
    {
        /** @var array<string, list<string>> $graph */
        $graph = $installedModules
            ->mapWithKeys(fn (Module $module) => [$module->name => array_keys($module->requiredModules())])
            ->all();

        $graph[$candidateName] = array_keys($requiredModules);

        $visited = [];
        $stack = [];

        $visit = function (string $node) use (&$visit, &$visited, &$stack, $graph): void {
            if (isset($stack[$node])) {
                throw ModuleDependencyCycleException::detected([...array_keys($stack), $node]);
            }

            if (isset($visited[$node])) {
                return;
            }

            $stack[$node] = true;

            foreach ($graph[$node] ?? [] as $dependency) {
                $visit($dependency);
            }

            unset($stack[$node]);
            $visited[$node] = true;
        };

        $visit($candidateName);
    }

    /**
     * @param  Collection<int, Module>  $activeModules
     */
    public function assertActivatable(Module $module, Collection $activeModules): void
    {
        foreach ($module->requiredModules() as $dependencyName => $constraint) {
            $dependency = $activeModules->firstWhere('name', $dependencyName);

            if (! $dependency instanceof Module) {
                throw IncompatibleModuleException::dependencyNotActive($module->name, $dependencyName);
            }

            if (! SemverConstraint::satisfiedBy($constraint, $dependency->version)) {
                throw IncompatibleModuleException::dependencyVersionMismatch(
                    $module->name,
                    $dependencyName,
                    $constraint,
                    $dependency->version,
                );
            }
        }
    }

    /**
     * @param  Collection<int, Module>  $activeModules
     */
    public function assertDeactivatable(Module $module, Collection $activeModules): void
    {
        $dependents = array_values($activeModules
            ->reject(fn (Module $candidate) => $candidate->is($module))
            ->filter(fn (Module $candidate) => array_key_exists($module->name, $candidate->requiredModules()))
            ->map(fn (Module $candidate) => $candidate->name)
            ->all());

        if ($dependents !== []) {
            throw ModuleHasActiveDependentsException::dependents($module->name, $dependents);
        }
    }
}
