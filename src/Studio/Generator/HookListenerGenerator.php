<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\StubRenderer;

/**
 * Génère les écouteurs squelettes déclarés au bloc `hooks.listens` d'un
 * blueprint Wizard Studio (spec-modules §5.2 étape 8) — concept de module,
 * pas d'entité, contrairement aux générateurs admin/front/API.
 * `hooks.emits` ne génère aucun fichier : c'est de la documentation/registre
 * pur, déjà consommé tel quel par `HookRegistry`/`bootstrapActiveModules()`
 * sans qu'aucune classe ne soit nécessaire côté module.
 */
final class HookListenerGenerator
{
    public function listener(string $hookName, string $className, string $namespace): string
    {
        return (new StubRenderer)->render(StudioStubs::path('hook-listener'), [
            'namespace' => $namespace,
            'hook_name' => $hookName,
            'class_name' => $className,
        ]);
    }
}
