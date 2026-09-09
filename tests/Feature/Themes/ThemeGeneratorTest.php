<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\Themes\Generator\ThemeGenerator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedThemePath());
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedThemePath());
    File::deleteDirectory(generatedModulesPath());
});

it('generates a complete theme skeleton on disk', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ]);

    $dir = generatedThemePath();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/screenshot.png"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/partials/header.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/partials/footer.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/assets/css/app.css"))->toBeTrue()
        ->and(File::isFile("{$dir}/vite.config.js"))->toBeTrue()
        ->and(File::isFile("{$dir}/package.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/src/Providers/SampleThemeServiceProvider.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/.baobab-checksums.json"))->toBeTrue();

    foreach (['index', 'single', 'archive', 'page', '404', '500', '503'] as $template) {
        expect(File::isFile("{$dir}/resources/views/templates/{$template}.blade.php"))->toBeTrue();
    }
});

it('writes a valid, correctly sized PNG for the screenshot placeholder', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ]);

    $info = getimagesize(generatedThemePath().'/screenshot.png');

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(1200)
        ->and($info[1])->toBe(900)
        ->and($info['mime'])->toBe('image/png');
});

it('honors a custom screenshot path declared by the blueprint', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'screenshot' => 'preview.png',
    ]);

    expect(File::isFile(generatedThemePath().'/preview.png'))->toBeTrue()
        ->and(File::isFile(generatedThemePath().'/screenshot.png'))->toBeFalse();
});

it('writes a module.json manifest consistent with the blueprint', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'menus' => ['primary' => 'Navigation principale'],
        'widget_zones' => ['sidebar' => 'Barre latérale'],
        'tokens' => ['colors' => ['primary' => '#123456']],
    ]);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get(generatedThemePath().'/module.json'), associative: true);

    expect($manifest['name'])->toBe('acme/sample-theme')
        ->and($manifest['title'])->toBe('Sample Theme')
        ->and($manifest['type'])->toBe('theme')
        ->and($manifest['provider'])->toBe('Themes\\SampleTheme\\Providers\\SampleThemeServiceProvider')
        ->and($manifest['autoload']['psr-4'])->toBe(['Themes\\SampleTheme\\' => 'src/'])
        ->and($manifest['theme']['parent'])->toBeNull()
        ->and($manifest['theme']['screenshot'])->toBe('screenshot.png')
        ->and($manifest['theme']['menus'])->toBe(['primary' => 'Navigation principale'])
        ->and($manifest['theme']['widget_zones'])->toBe(['sidebar' => 'Barre latérale'])
        ->and($manifest['tokens'])->toBe(['colors' => ['primary' => '#123456']]);
});

it('omits tokens and fonts from the manifest when the blueprint does not declare them', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ]);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get(generatedThemePath().'/module.json'), associative: true);

    expect($manifest)->not->toHaveKey('tokens')
        ->and($manifest)->not->toHaveKey('fonts');
});

it('regenerates silently when nothing has changed since the last generation', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint);
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint);

    expect(File::isFile(generatedThemePath().'/module.json'))->toBeTrue();
});

it('refuses to overwrite a generated file that was hand-edited', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint);

    File::append(generatedThemePath().'/resources/views/layouts/app.blade.php', "\n{{-- édité à la main --}}\n");

    expect(fn () => app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint))
        ->toThrow(GeneratedFileConflictException::class);
});

it('generates a single template with a field block per declared field, driven by content_types', function () {
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('Article', [
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'content_types' => [
            $contentType->key => ['templates' => ['show']],
        ],
    ]);

    $single = (string) File::get(generatedThemePath().'/resources/views/templates/single-article.blade.php');

    expect($single)->toContain("<x-baobab::field.text-display :value=\"\$entry->getAttribute('title')\" />")
        ->and($single)->toContain('<x-baobab::field.gallery-display :entry="$entry" field="photos" />')
        ->and(File::isFile(generatedThemePath().'/resources/views/templates/archive-article.blade.php'))->toBeFalse();
});

it('generates a search template and persists supports when "search" is declared', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'supports' => ['search'],
    ]);

    expect(File::isFile(generatedThemePath().'/resources/views/templates/search.blade.php'))->toBeTrue();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get(generatedThemePath().'/module.json'), associative: true);

    expect($manifest['theme']['supports'])->toBe(['search']);
});

it('does not generate a search template when "search" is not declared', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ]);

    expect(File::isFile(generatedThemePath().'/resources/views/templates/search.blade.php'))->toBeFalse();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get(generatedThemePath().'/module.json'), associative: true);

    expect($manifest['theme']['supports'])->toBe([]);
});

it('resolves a distinct stub path for the demo starter than for the sober default', function () {
    foreach (['layout', 'header', 'footer', 'app-css', 'template-index'] as $stub) {
        $soberPath = ThemeGenerator::stubPath($stub);
        $demoPath = ThemeGenerator::stubPath($stub, 'demo');

        expect($demoPath)->not->toBe($soberPath)
            ->and(File::isFile($soberPath))->toBeTrue()
            ->and(File::isFile($demoPath))->toBeTrue();
    }
});

it('generates a complete theme skeleton with --starter=demo, plumbing only at this pass', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo');

    $dir = generatedThemePath();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue();

    foreach (['index', 'single', 'archive', 'page', '404', '500', '503'] as $template) {
        expect(File::isFile("{$dir}/resources/views/templates/{$template}.blade.php"))->toBeTrue();
    }

    // Pass A ne fait que brancher l'option sur des stubs distincts, sans y
    // affirmer de style ni de contenu (suivi n° 284) : la sortie est donc
    // encore identique au squelette sobre, Pass B la fera diverger.
    File::deleteDirectory(generatedThemePath().'-sober');
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath().'-sober', $blueprint);

    expect(File::get("{$dir}/resources/views/layouts/app.blade.php"))
        ->toBe(File::get(generatedThemePath().'-sober/resources/views/layouts/app.blade.php'));

    File::deleteDirectory(generatedThemePath().'-sober');
});

it('generates an archive template with image/excerpt markup, driven by content_types', function () {
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('Article', [
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'cover', 'type' => 'image'],
            ['key' => 'summary', 'type' => 'textarea'],
        ],
    ]));

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'content_types' => [
            $contentType->key => ['templates' => ['index']],
        ],
    ]);

    $archive = (string) File::get(generatedThemePath().'/resources/views/templates/archive-article.blade.php');

    expect($archive)->toContain("<x-baobab::field.media-display :value=\"\$entry->getAttribute('cover')\" />")
        ->and($archive)->toContain("\$entry->getAttribute('summary')")
        ->and(File::isFile(generatedThemePath().'/resources/views/templates/single-article.blade.php'))->toBeFalse();
});
