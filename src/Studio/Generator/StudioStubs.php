<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

/**
 * Chemin des stubs du Wizard Studio (`resources/stubs/studio/`), distincts
 * de ceux des Content Types (`resources/stubs/content-type/`, patron
 * `StubRenderer::stubPath()`) — les deux générateurs partagent le même
 * moteur de rendu (`Baobab\ContentTypes\Generator\StubRenderer::render()`,
 * un simple remplacement de placeholders, sans dépendance au domaine) mais
 * pas le même répertoire de gabarits.
 */
final class StudioStubs
{
    public static function path(string $name): string
    {
        return dirname(__DIR__, 3)."/resources/stubs/studio/{$name}.stub";
    }
}
