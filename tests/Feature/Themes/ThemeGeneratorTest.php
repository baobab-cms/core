<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Forms\Models\Form;
use Baobab\Themes\Generator\ThemeGenerator;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

function themeGeneratorSuperAdmin(string $email = 'boss-theme-generator@example.com'): User
{
    $admin = User::create(['name' => 'Boss', 'email' => $email, 'password' => 'secret']);
    $admin->assignRole(Role::findByName('super-admin', 'baobab'));

    return $admin;
}

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

it('generates a complete theme skeleton with --starter=demo', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], 'demo');

    $dir = generatedThemePath();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue();

    foreach (['index', 'single', 'archive', 'page', '404', '500', '503'] as $template) {
        expect(File::isFile("{$dir}/resources/views/templates/{$template}.blade.php"))->toBeTrue();
    }
});

it('keeps the layout shell identical to the sober skeleton — pure plumbing, no style opinion', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo');

    File::deleteDirectory(generatedThemePath().'-sober');
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath().'-sober', $blueprint);

    expect(File::get(generatedThemePath().'/resources/views/layouts/app.blade.php'))
        ->toBe(File::get(generatedThemePath().'-sober/resources/views/layouts/app.blade.php'));

    File::deleteDirectory(generatedThemePath().'-sober');
});

/**
 * Pass B (suivi n° 284/285, direction « Savane » choisie avec l'utilisateur)
 * affirme le style du starter démonstratif à travers header/footer/templates
 * — jamais par des valeurs codées en dur, seulement par les rôles sémantiques
 * déjà exposés au thème (spec 18 §2.2 : un thème n'a jamais accès aux paliers
 * 50-900, réservés au chrome fixe de l'admin, §13.1). Deux usages du même
 * principe : le filet de chrome (header/footer, `primary` seul — la
 * « marque ») et le dégradé terre → feuillage → soleil (`primary` →
 * `secondary` → `accent`) qui signe chaque séparateur de contenu.
 */
it('applies the Savane chrome accent — primary only — to header and footer', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo');

    $dir = generatedThemePath();

    foreach ([
        'resources/views/partials/header.blade.php',
        'resources/views/partials/footer.blade.php',
    ] as $relativePath) {
        expect(File::get("{$dir}/{$relativePath}"))->toContain('primary/40');
    }
});

it('applies the Savane content signature — a terre/feuillage/soleil gradient — to every template', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo');

    $dir = generatedThemePath();

    foreach ([
        'resources/views/templates/index.blade.php',
        'resources/views/templates/single.blade.php',
        'resources/views/templates/page.blade.php',
        'resources/views/templates/archive.blade.php',
        'resources/views/templates/404.blade.php',
        'resources/views/templates/500.blade.php',
        'resources/views/templates/503.blade.php',
    ] as $relativePath) {
        expect(File::get("{$dir}/{$relativePath}"))->toContain('from-primary via-secondary to-accent');
    }
});

it('marks the publication date with the "état publié" role (secondary/success, spec 18 §13.3.1)', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], 'demo');

    expect(File::get(generatedThemePath().'/resources/views/templates/single.blade.php'))
        ->toContain('text-secondary');
});

it('diverges from the sober skeleton on header, footer and templates', function () {
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo');

    File::deleteDirectory(generatedThemePath().'-sober');
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath().'-sober', $blueprint);

    foreach ([
        'resources/views/partials/header.blade.php',
        'resources/views/partials/footer.blade.php',
        'resources/views/templates/index.blade.php',
    ] as $relativePath) {
        expect(File::get(generatedThemePath()."/{$relativePath}"))
            ->not->toBe(File::get(generatedThemePath()."-sober/{$relativePath}"));
    }

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

/**
 * Pass C (suivi n° 284/288) : le contenu de démonstration, posé par la
 * commande elle-même — `SeedDemoContent` (spec 19 §7.2) réutilisée telle
 * quelle, mécanisme d'installation/retrait compris, jamais réécrite. Les
 * gabarits pilotés par les champs (`single-page`, `single-article`,
 * `archive-article`) sont auto-déclarés pour que le rendu soit complet dès
 * ce seul passage, sans que le développeur ait à connaître `Page`/`Article`.
 */
it('seeds the demo content and auto-generates field-aware Page/Article templates for --starter=demo', function () {
    $admin = themeGeneratorSuperAdmin();

    $outcome = app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], 'demo', $admin);

    $dir = generatedThemePath();

    expect($outcome)->toContain('Content Types créés : Page, Article.')
        ->and(ContentType::where('key', 'Page')->exists())->toBeTrue()
        ->and(ContentType::where('key', 'Article')->exists())->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/single-page.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/single-article.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/archive-article.blade.php"))->toBeTrue();

    // Gabarit piloté par les champs réels de la démo (excerpt/body/cover),
    // pas le repli générique de la Pass B (qui ignore le contenu).
    expect((string) File::get("{$dir}/resources/views/templates/single-article.blade.php"))
        ->toContain("\$entry->getAttribute('body')");
});

/**
 * Pass D (suivi n° 284) : le formulaire de contact de démonstration, posé
 * par `SeedDemoForm` (spec 14 §8) et embarqué directement dans le stub du
 * pied de page — jamais via un embed richtext (choix tranché avec
 * l'utilisateur en ouvrant la passe : le nœud Tiptap dédié à l'embarquement
 * d'un formulaire n'était exercé nulle part ailleurs dans le code).
 */
it('seeds the demo contact form for --starter=demo', function () {
    $admin = themeGeneratorSuperAdmin();

    $outcome = app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], 'demo', $admin);

    expect($outcome)->toContain('Formulaire de démonstration créé : Contact.')
        ->and(Form::where('slug', 'contact')->exists())->toBeTrue();
});

it('embeds the demo contact form in the generated footer stub', function () {
    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], 'demo');

    expect(File::get(generatedThemePath().'/resources/views/partials/footer.blade.php'))
        ->toContain('<x-baobab::form-embed slug="contact"');
});

it('does not seed demo content for the sober skeleton, even when an actor is given', function () {
    $admin = themeGeneratorSuperAdmin();

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
    ], null, $admin);

    expect(ContentType::where('key', 'Page')->exists())->toBeFalse()
        ->and(ContentType::where('key', 'Article')->exists())->toBeFalse()
        ->and(Form::where('slug', 'contact')->exists())->toBeFalse();
});

it('lets an explicit content_types declaration narrow the auto-added demo entry', function () {
    $admin = themeGeneratorSuperAdmin();

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), [
        'name' => 'Sample Theme',
        'slug' => 'sample-theme',
        'content_types' => [
            'Page' => ['templates' => ['show']],
        ],
    ], 'demo', $admin);

    $dir = generatedThemePath();

    expect(File::isFile("{$dir}/resources/views/templates/single-page.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/archive-page.blade.php"))->toBeFalse()
        // Article n'a pas été déclaré explicitement : l'ajout automatique s'applique toujours à lui.
        ->and(File::isFile("{$dir}/resources/views/templates/archive-article.blade.php"))->toBeTrue();
});

it('is idempotent across repeated --starter=demo runs, without duplicating content', function () {
    $admin = themeGeneratorSuperAdmin();
    $blueprint = ['name' => 'Sample Theme', 'slug' => 'sample-theme'];

    app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo', $admin);
    $second = app(ThemeGenerator::class)('acme/sample-theme', generatedThemePath(), $blueprint, 'demo', $admin);

    expect($second)->toContain('Le contenu de démonstration est déjà en place.')
        ->and($second)->toContain('Le formulaire de démonstration est déjà en place.');
});
