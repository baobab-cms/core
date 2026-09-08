<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

/**
 * Remplacement de placeholders simple sur un fichier .stub — même idiome que
 * les stubs make:model de Laravel, sans moteur de template ajouté en
 * dépendance.
 */
final class StubRenderer
{
    /**
     * @param  array<string, string>  $replacements  Nom du placeholder (sans accolades) → valeur.
     */
    public function render(string $stubPath, array $replacements): string
    {
        $contents = (string) file_get_contents($stubPath);

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        return $contents;
    }

    public static function stubPath(string $name): string
    {
        return dirname(__DIR__, 3)."/resources/stubs/content-type/{$name}.stub";
    }
}
