<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Install\Actions\CreateSuperAdmin;
use Baobab\Modules\ModuleManifest;
use Baobab\Themes\Blueprint\ThemeBlueprintValidator;
use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;
use Baobab\Themes\Generator\ThemeGenerator;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;
use Baobab\Themes\Validation\ThemeValidator;
use Baobab\Users\Models\User;
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
 *
 * `--starter=demo` (spec 17 §4, §9 décision 1, M8 point 5, chantier 6, suivi
 * n° 284) sélectionne un jeu de stubs distinct du squelette sobre (Pass A),
 * y affirme un style (Pass B) et pose le contenu de démonstration (Pass C) —
 * `Page`/`Article` créés et peuplés par `SeedDemoContent` (spec 19 §7.2,
 * réutilisée telle quelle, mécanisme d'installation/retrait compris),
 * attribués au premier super-admin trouvé : `SaveContentEntry` exige un
 * acteur (`author_id`), et rien hors contexte HTTP n'en fournit un. Sur une
 * base vraiment vierge (jamais passée par `baobab:install`, aucun compte),
 * `--admin-email=` en fait créer un à la volée (même `CreateSuperAdmin` que
 * `baobab:super-admin`, mot de passe généré affiché en clair) plutôt que de
 * casser la promesse d'autonomie de la spec 17 §4 — sans l'option, échec
 * explicite plutôt que de deviner un utilisateur. Un super-admin déjà
 * existant l'emporte toujours : `--admin-email` n'est lu que dans son
 * absence, jamais pour en créer un second (suivi n° 288).
 */
final class ThemeMakeCommand extends Command
{
    protected $signature = 'baobab:make:theme {name : The theme name (vendor/slug)} {--starter= : Starter variant — "demo" (spec 17 §4); sober skeleton by default} {--admin-email= : Only with --starter=demo, when no super-admin exists yet — creates one to attribute the demo content to}';

    protected $description = 'Generate a theme from its theme.json blueprint (spec 17).';

    public function handle(ThemeBlueprintValidator $blueprintValidator, ThemeGenerator $generator, ThemeValidator $themeValidator, CreateSuperAdmin $createSuperAdmin): int
    {
        /** @var string $name */
        $name = $this->argument('name');
        /** @var string|null $starter */
        $starter = $this->option('starter');

        if ($starter !== null && $starter !== 'demo') {
            $this->error("Unknown starter [{$starter}] — only \"demo\" is supported (spec 17 §4).");

            return self::FAILURE;
        }

        $actor = null;

        if ($starter === 'demo') {
            $actor = User::role(CreateSuperAdmin::ROLE, CreateSuperAdmin::GUARD)->first();

            if ($actor instanceof User && $this->option('admin-email') !== null) {
                $this->line("  --admin-email ignoré : un super-admin existe déjà ({$actor->email}).");
            }

            if (! $actor instanceof User) {
                /** @var string|null $adminEmail */
                $adminEmail = $this->option('admin-email');

                if ($adminEmail === null) {
                    $this->error('--starter=demo requires an existing super-admin to attribute the demo content to — run `php artisan baobab:super-admin {email}` first, or pass --admin-email= to create one.');

                    return self::FAILURE;
                }

                $result = $createSuperAdmin($adminEmail);
                $actor = $result->user;

                $this->line("  Super-admin créé : <fg=cyan>{$adminEmail}</>");

                if ($result->generatedPassword !== null) {
                    $this->line("  Mot de passe généré : <fg=yellow>{$result->generatedPassword}</> (non récupérable)");
                }
            }
        }

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

        $demoOutcome = $generator($name, $themeDir, $blueprint, $starter, $actor);

        foreach ($demoOutcome as $line) {
            $this->line("  {$line}");
        }

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
