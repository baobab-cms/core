<?php

declare(strict_types=1);

namespace Baobab\Themes\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\GeneratedFileChecksums;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Transforme un blueprint `theme.json` validé en thème complet sur disque
 * (spec 17 §3-4, M8 point 5 Pass A) : squelette (toujours) + templates
 * pilotés par les Content Types déclarés (`content_types`). Patron exact
 * `Baobab\ContentTypes\Generator\ContentTypeModuleGenerator` — écriture
 * exclusivement via `GeneratedFileChecksums::write()`, jamais un accès
 * disque direct, pour bénéficier gratuitement de la régénération non
 * destructive (spec 17 §6.2).
 */
final class ThemeGenerator
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly GeneratedFileChecksums $checksums,
        private readonly FieldRegistry $fields,
    ) {}

    /**
     * @param  array<string, mixed>  $blueprint  Blueprint theme.json décodé (associatif), déjà validé.
     */
    public function __invoke(string $name, string $themeDir, array $blueprint): void
    {
        $this->generateSkeleton($name, $themeDir, $blueprint);

        /** @var array<string, array{templates?: list<string>}> $contentTypes */
        $contentTypes = $blueprint['content_types'] ?? [];

        foreach ($contentTypes as $key => $config) {
            $contentType = ContentType::where('key', $key)->firstOrFail();
            $templates = $config['templates'] ?? ['show', 'index'];

            if (in_array('show', $templates, true)) {
                $this->generateShowTemplate($contentType, $themeDir);
            }

            if (in_array('index', $templates, true)) {
                $this->generateIndexTemplate($contentType, $themeDir);
            }
        }
    }

    public static function stubPath(string $name): string
    {
        return dirname(__DIR__, 3)."/ressources/stubs/theme/{$name}.stub";
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function generateSkeleton(string $name, string $themeDir, array $blueprint): void
    {
        $siteName = addslashes((string) ($blueprint['name'] ?? $name));
        $studly = Str::studly((string) Str::afterLast($name, '/'));
        $namespace = "Themes\\{$studly}";
        $className = "{$studly}ServiceProvider";
        $dirSlug = basename($themeDir);

        $this->checksums->write($themeDir, 'module.json', $this->moduleJson($name, $namespace, $className, $blueprint));

        $this->checksums->write(
            $themeDir,
            (string) ($blueprint['screenshot'] ?? 'screenshot.png'),
            $this->screenshotPlaceholder((string) ($blueprint['name'] ?? $name)),
        );

        $this->checksums->write($themeDir, 'resources/views/layouts/app.blade.php', $this->renderer->render(
            self::stubPath('layout'),
            ['site_name' => $siteName],
        ));

        $this->checksums->write($themeDir, 'resources/views/partials/header.blade.php', $this->renderer->render(
            self::stubPath('header'),
            ['site_name' => $siteName, 'primary_menu' => $this->primaryMenuBlock($blueprint)],
        ));

        $this->checksums->write($themeDir, 'resources/views/partials/footer.blade.php', $this->renderer->render(
            self::stubPath('footer'),
            [
                'site_name' => $siteName,
                'widget_zones' => $this->widgetZonesBlock($blueprint),
                'other_menus' => $this->otherMenusBlock($blueprint),
            ],
        ));

        foreach (['index', 'single', 'archive', 'page', '404', '500', '503'] as $template) {
            $this->checksums->write($themeDir, "resources/views/templates/{$template}.blade.php", $this->renderer->render(
                self::stubPath("template-{$template}"),
                ['site_name' => $siteName],
            ));
        }

        $this->checksums->write($themeDir, 'resources/assets/css/app.css', $this->renderer->render(self::stubPath('app-css'), []));

        $this->checksums->write($themeDir, 'vite.config.js', $this->renderer->render(self::stubPath('vite-config'), ['dir_slug' => $dirSlug]));

        $this->checksums->write($themeDir, 'package.json', $this->renderer->render(self::stubPath('package-json'), [
            'npm_name' => (string) ($blueprint['slug'] ?? $dirSlug),
        ]));

        $this->checksums->write($themeDir, "src/Providers/{$className}.php", $this->renderer->render(
            self::stubPath('provider'),
            ['namespace' => $namespace, 'class_name' => $className],
        ));
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function moduleJson(string $name, string $namespace, string $className, array $blueprint): string
    {
        $theme = [
            'parent' => null,
            'screenshot' => $blueprint['screenshot'] ?? 'screenshot.png',
            // (object) : un blueprint sans menu/zone déclaré doit produire
            // `{}` dans module.json (module.schema.json §theme.menus attend
            // un objet) — un tableau PHP vide s'encoderait en `[]`, rejeté.
            'menus' => (object) ($blueprint['menus'] ?? []),
            'widget_zones' => (object) ($blueprint['widget_zones'] ?? []),
        ];

        $manifest = [
            'name' => $name,
            'title' => $blueprint['name'] ?? $name,
            'version' => $blueprint['version'] ?? '1.0.0',
            'type' => 'theme',
            'provider' => "{$namespace}\\Providers\\{$className}",
            'autoload' => [
                'psr-4' => ["{$namespace}\\" => 'src/'],
            ],
            'theme' => $theme,
        ];

        if (isset($blueprint['tokens'])) {
            $manifest['tokens'] = $blueprint['tokens'];
        }

        if (isset($blueprint['fonts'])) {
            $manifest['fonts'] = $blueprint['fonts'];
        }

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Capture d'écran placeholder (spec 17 §6.2/§7 — `ThemeValidator` la
     * requiert de façon bloquante). Police bitmap intégrée à GD (`imagestring`)
     * plutôt qu'une police TTF : aucun fichier de police à embarquer/charger.
     * Régénérée par le mécanisme de checksum standard tant que le développeur
     * ne l'a pas remplacée par une vraie capture.
     */
    private function screenshotPlaceholder(string $siteName): string
    {
        $width = 1200;
        $height = 900;

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Impossible de générer le placeholder de capture d\'écran (extension GD).');
        }

        $background = imagecolorallocate($image, 24, 24, 27);
        $foreground = imagecolorallocate($image, 244, 244, 245);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) $background);

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($siteName);
        $textHeight = imagefontheight($font);
        imagestring($image, $font, intdiv($width - $textWidth, 2), intdiv($height - $textHeight, 2), $siteName, (int) $foreground);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();

        imagedestroy($image);

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function primaryMenuBlock(array $blueprint): string
    {
        /** @var array<string, string> $menus */
        $menus = $blueprint['menus'] ?? [];

        if (! array_key_exists('primary', $menus)) {
            return '';
        }

        return '        <x-baobab::menu location="primary" class="flex flex-wrap items-center gap-6 text-sm font-medium text-muted [&_a.active]:text-foreground [&_a:hover]:text-foreground" />';
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function otherMenusBlock(array $blueprint): string
    {
        /** @var array<string, string> $menus */
        $menus = $blueprint['menus'] ?? [];

        $lines = [];

        foreach (array_keys($menus) as $location) {
            if ($location === 'primary') {
                continue;
            }

            $lines[] = "            <x-baobab::menu location=\"{$location}\" class=\"flex flex-wrap gap-4 [&_a:hover]:text-foreground\" />";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function widgetZonesBlock(array $blueprint): string
    {
        /** @var array<string, string> $zones */
        $zones = $blueprint['widget_zones'] ?? [];

        $lines = [];

        foreach (array_keys($zones) as $zone) {
            $lines[] = "        <x-baobab::widget-zone name=\"{$zone}\" class=\"grid gap-8 sm:grid-cols-2 lg:grid-cols-3\" />";
        }

        return implode("\n", $lines);
    }

    private function generateShowTemplate(ContentType $contentType, string $themeDir): void
    {
        $key = Str::kebab($contentType->key);
        /** @var list<array{key: string, type: string, options?: array<string, mixed>}> $fields */
        $fields = $contentType->blueprint['fields'] ?? [];

        $blocks = collect($fields)
            ->map(fn (array $field): string => $this->fieldBlock($field))
            ->implode("\n\n");

        $content = <<<BLADE
        @extends('theme::layouts.app')

        @section('title', \$title)

        @section('content')
            <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <h1 class="font-display text-3xl font-semibold tracking-tight text-foreground">{{ \$title }}</h1>

        {$blocks}
            </article>
        @endsection

        BLADE;

        $this->checksums->write($themeDir, "resources/views/templates/single-{$key}.blade.php", $content);
    }

    /**
     * @param  array{key: string, type: string, options?: array<string, mixed>}  $field
     */
    private function fieldBlock(array $field): string
    {
        $key = $field['key'];
        $type = $this->fields->resolve($field['type']);
        $tag = Str::after($type->displayComponent(), '::');

        $inner = match ($field['type']) {
            'gallery' => "<x-baobab::{$tag} :entry=\"\$entry\" field=\"{$key}\" />",
            default => "<x-baobab::{$tag} :value=\"\$entry->getAttribute('{$key}')\" />",
        };

        return <<<BLADE
                <div class="field-{$key} mt-6">
                    <h2 class="font-display text-sm font-medium text-muted">{$key}</h2>
                    {$inner}
                </div>
        BLADE;
    }

    private function generateIndexTemplate(ContentType $contentType, string $themeDir): void
    {
        $key = Str::kebab($contentType->key);
        /** @var list<array{key: string, type: string}> $fields */
        $fields = $contentType->blueprint['fields'] ?? [];
        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;

        $imageField = collect($fields)->first(fn (array $field): bool => $field['type'] === 'image');
        $excerptField = collect($fields)->first(fn (array $field): bool => in_array($field['type'], ['textarea', 'richtext'], true));

        $titleExpression = $titleField !== null
            ? "\$entry->getAttribute('{$titleField}')"
            : "\$entry->getAttribute('slug')";

        $imageMarkup = $imageField !== null
            ? "                    <x-baobab::field.media-display :value=\"\$entry->getAttribute('{$imageField['key']}')\" />\n"
            : '';

        $excerptMarkup = $excerptField !== null
            ? "                    <p class=\"mt-2 text-sm text-muted\">{{ \\Illuminate\\Support\\Str::limit(strip_tags((string) \$entry->getAttribute('{$excerptField['key']}')), 160) }}</p>\n"
            : '';

        $content = <<<BLADE
        @extends('theme::layouts.app')

        @section('title', \$title)

        @section('content')
            <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
                <h1 class="font-display text-3xl font-semibold tracking-tight text-foreground">{{ \$title }}</h1>

                @if (\$entries->isEmpty())
                    <p class="mt-6 text-muted">Aucun contenu publié pour le moment.</p>
                @else
                    <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (\$entries as \$entry)
                            <article>
        {$imageMarkup}                    <h2 class="mt-3 font-display text-lg font-medium text-foreground">
                                    <a href="{{ rtrim(request()->url(), '/') }}/{{ \$entry->getAttribute('slug') }}" class="hover:text-muted">{{ {$titleExpression} }}</a>
                                </h2>
        {$excerptMarkup}            </article>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ \$entries->links() }}
                    </div>
                @endif
            </div>
        @endsection

        BLADE;

        $this->checksums->write($themeDir, "resources/views/templates/archive-{$key}.blade.php", $content);
    }
}
