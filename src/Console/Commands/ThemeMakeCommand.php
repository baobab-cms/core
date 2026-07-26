<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\ModuleManifest;
use Baobab\Themes\Blueprint\ThemeBlueprintValidator;
use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;
use Baobab\Themes\Generator\ThemeGenerator;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;
use Baobab\Themes\Validation\ThemeValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * `php artisan baobab:make:theme {name}` (spec 17 §1, §8, M8 point 5 Pass A) —
 * `{name}` = vendor/slug, cohérent avec `theme:activate`/`theme:preview`/
 * `theme:validate` (adaptation déjà actée pour ces trois commandes, pas le
 * `{slug}` nu de la lettre de la spec). Le développeur crée `theme.json` à la
 * main dans `themes/{dernier segment de name}/` avant de lancer la commande
 * (narrowing Pass A — le mode interactif sans fichier de spec 17 §1 reste à
 * construire) ; conformité par construction (spec 17 §6.1) vérifiée via le
 * même `ThemeValidator` que `baobab:theme:validate`.
 */
final class ThemeMakeCommand extends Command
{
    protected $signature = 'baobab:make:theme {name : The theme name (vendor/slug)}';

    protected $description = 'Generate a theme from its theme.json blueprint (spec 17).';

    public function handle(ThemeBlueprintValidator $blueprintValidator, ThemeGenerator $generator, ThemeValidator $themeValidator): int
    {
        /** @var string $name */
        $name = $this->argument('name');
        $dirSlug = Str::afterLast($name, '/');
        $themeDir = base_path("themes/{$dirSlug}");
        $blueprintPath = "{$themeDir}/theme.json";

        if (! File::isFile($blueprintPath)) {
            $this->error("No theme.json found at {$blueprintPath} — create it first (spec 17 §2).");

            return self::FAILURE;
        }

        $json = (string) File::get($blueprintPath);

        try {
            $blueprintValidator->validate($json);
        } catch (InvalidThemeBlueprintException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $blueprint */
        $blueprint = json_decode($json, associative: true);

        $generator($name, $themeDir, $blueprint);

        $manifest = ModuleManifest::fromJson((string) File::get("{$themeDir}/module.json"));

        try {
            $themeValidator->assertValid($manifest, $themeDir);
        } catch (ThemeValidationFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Theme [{$name}] generated at {$themeDir}.");

        return self::SUCCESS;
    }
}
