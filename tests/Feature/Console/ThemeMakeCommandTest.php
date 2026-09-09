<?php

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

function themeMakeCommandSuperAdmin(): User
{
    $admin = User::create(['name' => 'Boss', 'email' => 'boss-theme-make-command@example.com', 'password' => 'secret']);
    $admin->assignRole(Role::findByName('super-admin', 'baobab'));

    return $admin;
}

/**
 * `ThemeMakeCommand` calcule `base_path("themes/{dirSlug}")` lui-même — pas
 * de configuration injectable, contrairement à `ThemeGenerator` (testé
 * séparément via `generatedThemePath()`). On écrit donc réellement sous
 * `themes/`, avec un slug dédié aux tests, nettoyé dans `afterEach()`.
 * `themes/` est gitignoré (contenu local de banc d'essai, spec 17 §1).
 */
function themeMakeCommandDir(): string
{
    return base_path('themes/pest-theme-make-test');
}

function writeThemeMakeCommandBlueprint(array $blueprint): void
{
    File::ensureDirectoryExists(themeMakeCommandDir());
    File::put(themeMakeCommandDir().'/theme.json', (string) json_encode($blueprint));
}

beforeEach(function () {
    File::deleteDirectory(themeMakeCommandDir());
});

afterEach(function () {
    File::deleteDirectory(themeMakeCommandDir());
});

it('fails clearly when no theme.json exists yet', function () {
    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No theme.json found');
});

it('fails and reports validation errors for an invalid blueprint', function () {
    writeThemeMakeCommandBlueprint(['name' => 'Pest Theme']); // slug manquant, requis par le schéma

    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Blueprint de thème invalide');
});

it('rejects an unknown --starter value', function () {
    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', [
        'name' => 'acme/pest-theme-make-test',
        '--starter' => 'blank',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Unknown starter');
});

it('fails clearly when --starter=demo has no super-admin to attribute the demo content to', function () {
    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', [
        'name' => 'acme/pest-theme-make-test',
        '--starter' => 'demo',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('requires an existing super-admin');
});

it('auto-creates a super-admin via --admin-email when none exists yet, and attributes the demo content to it', function () {
    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', [
        'name' => 'acme/pest-theme-make-test',
        '--starter' => 'demo',
        '--admin-email' => 'fresh-admin@example.com',
    ]);

    expect($exitCode)->toBe(0);

    $admin = User::where('email', 'fresh-admin@example.com')->firstOrFail();

    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and(ContentType::where('key', 'Article')->exists())->toBeTrue();
});

it('forces the existing super-admin and ignores --admin-email when one already exists', function () {
    $existing = themeMakeCommandSuperAdmin();

    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', [
        'name' => 'acme/pest-theme-make-test',
        '--starter' => 'demo',
        '--admin-email' => 'should-not-be-created@example.com',
    ]);

    expect($exitCode)->toBe(0)
        ->and(User::where('email', 'should-not-be-created@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe(1);

    $article = ContentType::where('key', 'Article')->firstOrFail();
    $entry = $article->modelClass()::query()->firstOrFail();

    expect($entry->author_id)->toBe($existing->id);
});

it('generates a valid theme end to end with --starter=demo, seeding the demo content, and reports success', function () {
    themeMakeCommandSuperAdmin();

    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    // `Artisan::output()` ne reflète que le dernier sous-appel Artisan
    // imbriqué (ici la migration déclenchée par BuildContentType via
    // SeedDemoContent) — le succès se vérifie donc sur le code de sortie et
    // les effets réels, pas sur le buffer de sortie.
    $exitCode = Artisan::call('baobab:make:theme', [
        'name' => 'acme/pest-theme-make-test',
        '--starter' => 'demo',
    ]);

    expect($exitCode)->toBe(0);

    $dir = themeMakeCommandDir();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/single-article.blade.php"))->toBeTrue()
        ->and(ContentType::where('key', 'Article')->exists())->toBeTrue();
});

it('generates a valid theme end to end and reports success', function () {
    writeThemeMakeCommandBlueprint([
        'name' => 'Pest Theme',
        'slug' => 'pest-theme-make-test',
    ]);

    $exitCode = Artisan::call('baobab:make:theme', ['name' => 'acme/pest-theme-make-test']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('generated at');

    $dir = themeMakeCommandDir();

    expect(File::isFile("{$dir}/module.json"))->toBeTrue()
        ->and(File::isFile("{$dir}/screenshot.png"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/layouts/app.blade.php"))->toBeTrue()
        ->and(File::isFile("{$dir}/resources/views/templates/index.blade.php"))->toBeTrue();

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) File::get("{$dir}/module.json"), associative: true);

    expect($manifest['name'])->toBe('acme/pest-theme-make-test');
});
