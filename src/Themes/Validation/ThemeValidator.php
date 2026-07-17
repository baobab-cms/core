<?php

declare(strict_types=1);

namespace Baobab\Themes\Validation;

use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleManifest;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpParser\Error as PhpParserError;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Validateur de thèmes (spec 03 §10.3) — niveau 1 (structurel) et niveau 2
 * (motifs interdits/avertissements, AST via nikic/php-parser). Le niveau 3
 * (marketplace : signature, score de qualité) est hors périmètre, aucun
 * marketplace n'existe (phase Plateforme, post-v1).
 */
final class ThemeValidator
{
    private const WARNING_LINE_THRESHOLD = 20;

    /**
     * @return list<ThemeViolation>
     */
    public function validate(ModuleManifest $manifest, string $path): array
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return [
            ...$this->checkRequiredFiles($manifest, $path),
            ...$this->checkParentTheme($manifest),
            ...$this->checkNoPhpInPublicOrAssets($path),
            ...$this->checkBladePhpTags($path),
            ...$this->checkForbiddenConstructs($manifest, $path),
            ...$this->checkBladeWarnings($path),
            ...$this->checkSeoHeadPresence($manifest, $path),
        ];
    }

    /**
     * @throws ThemeValidationFailedException
     */
    public function assertValid(ModuleManifest $manifest, string $path): void
    {
        $blocking = array_values(array_filter(
            $this->validate($manifest, $path),
            fn (ThemeViolation $violation): bool => $violation->blocking,
        ));

        if ($blocking !== []) {
            throw ThemeValidationFailedException::forViolations($manifest->name(), $blocking);
        }
    }

    /**
     * Fichiers requis (spec 03 §2) — un thème enfant peut les omettre s'ils
     * sont hérités du parent (spec 03 §6 : « toute vue... absente de
     * l'enfant est cherchée dans le parent »), à l'exception de la capture
     * d'écran, propre à chaque thème (y compris un enfant, qui a sa propre
     * apparence).
     *
     * @return list<ThemeViolation>
     */
    private function checkRequiredFiles(ModuleManifest $manifest, string $path): array
    {
        $violations = [];
        $parentPath = $this->parentPath($manifest);

        $required = [
            'resources/views/layouts/app.blade.php' => 'Layout racine manquant (resources/views/layouts/app.blade.php, spec 03 §2).',
            'resources/views/templates/index.blade.php' => 'Template de repli manquant (resources/views/templates/index.blade.php, spec 03 §4).',
        ];

        foreach ($required as $relative => $message) {
            $inherited = $parentPath !== null && is_file("{$parentPath}/{$relative}");

            if (! is_file("{$path}/{$relative}") && ! $inherited) {
                $violations[] = new ThemeViolation($relative, null, $message, blocking: true);
            }
        }

        /** @var string $screenshot */
        $screenshot = $manifest->theme()['screenshot'] ?? 'screenshot.png';

        if (! is_file("{$path}/{$screenshot}")) {
            $violations[] = new ThemeViolation($screenshot, null, "Capture d'écran manquante ({$screenshot}).", blocking: true);
        }

        return $violations;
    }

    /**
     * @return list<ThemeViolation>
     */
    private function checkParentTheme(ModuleManifest $manifest): array
    {
        /** @var string|null $parentName */
        $parentName = $manifest->theme()['parent'] ?? null;

        if ($parentName === null) {
            return [];
        }

        if ($this->parentPath($manifest) === null) {
            return [new ThemeViolation(
                'module.json',
                null,
                "Thème parent déclaré introuvable ou non installé : {$parentName}.",
                blocking: true,
            )];
        }

        return [];
    }

    private function parentPath(ModuleManifest $manifest): ?string
    {
        /** @var string|null $parentName */
        $parentName = $manifest->theme()['parent'] ?? null;

        if ($parentName === null) {
            return null;
        }

        $parent = Module::where('name', $parentName)->where('type', 'theme')->first();

        return $parent instanceof Module ? rtrim(str_replace('\\', '/', $parent->path), '/') : null;
    }

    /**
     * @return list<ThemeViolation>
     */
    private function checkNoPhpInPublicOrAssets(string $path): array
    {
        $violations = [];

        foreach (['public', 'resources/assets'] as $dir) {
            if (! is_dir("{$path}/{$dir}")) {
                continue;
            }

            foreach (File::allFiles("{$path}/{$dir}") as $file) {
                if ($file->getExtension() === 'php') {
                    $relative = $this->relative($path, $file->getPathname());
                    $violations[] = new ThemeViolation(
                        $relative,
                        null,
                        "Fichier PHP interdit hors des répertoires de code attendus : {$relative}.",
                        blocking: true,
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<ThemeViolation>
     */
    private function checkBladePhpTags(string $path): array
    {
        $violations = [];

        foreach ($this->bladeFiles($path) as $relative => $contents) {
            if (str_contains($contents, '@php')) {
                $violations[] = new ThemeViolation($relative, null, 'Directive @php interdite dans une vue de thème.', blocking: true);
            }

            if (str_contains($contents, '<?php') || str_contains($contents, '<?=')) {
                $violations[] = new ThemeViolation($relative, null, 'Balise PHP brute interdite dans une vue de thème.', blocking: true);
            }
        }

        return $violations;
    }

    /**
     * @return list<ThemeViolation>
     */
    private function checkForbiddenConstructs(ModuleManifest $manifest, string $path): array
    {
        $violations = [];
        $tablePrefix = Str::kebab(Str::afterLast($manifest->name(), '/'));
        $parser = (new ParserFactory)->createForHostVersion();

        foreach ($this->phpFiles($path) as $relative => $contents) {
            try {
                $ast = $parser->parse($contents) ?? [];
            } catch (PhpParserError $e) {
                $violations[] = new ThemeViolation($relative, null, "Fichier PHP illisible : {$e->getMessage()}", blocking: true);

                continue;
            }

            $visitor = new ForbiddenConstructVisitor($relative, $tablePrefix);
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            array_push($violations, ...$visitor->violations());
        }

        return $violations;
    }

    /**
     * @return list<ThemeViolation>
     */
    private function checkBladeWarnings(string $path): array
    {
        $violations = [];

        foreach ($this->bladeFiles($path) as $relative => $contents) {
            if (preg_match('/\w+::(where|find|all|create|update|delete)\(/', $contents) === 1) {
                $violations[] = new ThemeViolation(
                    $relative,
                    null,
                    'Requête Eloquent probable dans une vue — les données doivent être préparées par le Core/les modules (spec 03 §3).',
                    blocking: false,
                );
            }

            if (preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $contents, $matches) > 0) {
                foreach ($matches[1] as $body) {
                    if (substr_count((string) $body, "\n") > self::WARNING_LINE_THRESHOLD) {
                        $violations[] = new ThemeViolation($relative, null, 'JavaScript inline volumineux — à externaliser dans les assets du thème.', blocking: false);
                    }
                }
            }

            if (preg_match('#<script[^>]+src=["\']https?://#', $contents) === 1) {
                $violations[] = new ThemeViolation($relative, null, 'Script externe (CDN) non déclaré au manifeste.', blocking: false);
            }
        }

        return $violations;
    }

    /**
     * Le thème n'écrit lui-même aucune balise SEO — il place
     * `<x-baobab::seo-head />` dans son layout, le Core compose le reste
     * (spec 07 §6 dernière phrase). Non bloquant (contrairement aux
     * fichiers requis, `checkRequiredFiles`) : un thème sans ce composant
     * reste fonctionnel, juste moins bien référencé. Même repli parent/
     * enfant que le layout lui-même (spec 03 §6).
     *
     * @return list<ThemeViolation>
     */
    private function checkSeoHeadPresence(ModuleManifest $manifest, string $path): array
    {
        $relative = 'resources/views/layouts/app.blade.php';
        $parentPath = $this->parentPath($manifest);

        $layoutPath = match (true) {
            is_file("{$path}/{$relative}") => "{$path}/{$relative}",
            $parentPath !== null && is_file("{$parentPath}/{$relative}") => "{$parentPath}/{$relative}",
            default => null,
        };

        if ($layoutPath === null) {
            return [];
        }

        $contents = (string) file_get_contents($layoutPath);

        if (str_contains($contents, '<x-baobab::seo-head')) {
            return [];
        }

        return [new ThemeViolation($relative, null, 'Composant <x-baobab::seo-head /> absent du layout — le thème ne fournira aucune balise SEO (spec 07 §6).', blocking: false)];
    }

    /**
     * @return array<string, string> Chemin relatif → contenu.
     */
    private function bladeFiles(string $path): array
    {
        if (! is_dir("{$path}/resources/views")) {
            return [];
        }

        $files = [];

        foreach (File::allFiles("{$path}/resources/views") as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[$this->relative($path, $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    /**
     * @return array<string, string> Chemin relatif → contenu, hors public/ et
     *                               resources/assets/ (déjà signalés par
     *                               checkNoPhpInPublicOrAssets()).
     */
    private function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = $this->relative($path, $file->getPathname());

            if (str_starts_with($relative, 'public/') || str_starts_with($relative, 'resources/assets/')) {
                continue;
            }

            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    private function relative(string $themePath, string $absolute): string
    {
        return Str::after(str_replace('\\', '/', $absolute), "{$themePath}/");
    }
}
