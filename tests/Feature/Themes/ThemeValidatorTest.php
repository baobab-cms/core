<?php

use Baobab\Modules\ModuleManifest;
use Baobab\Themes\Validation\ThemeValidator;
use Baobab\Themes\Validation\ThemeViolation;
use Illuminate\Support\Facades\File;

function themeFixturePath(): string
{
    return sys_get_temp_dir().'/baobab-test-theme-validator';
}

/**
 * @param  array<string, mixed>  $overrides
 */
function themeManifest(array $overrides = []): ModuleManifest
{
    return ModuleManifest::fromJson((string) json_encode(array_replace([
        'name' => 'acme/validator-fixture',
        'title' => 'Validator Fixture',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\ValidatorFixture\\Providers\\ThemeServiceProvider',
        'theme' => ['parent' => null],
    ], $overrides)));
}

function writeValidThemeSkeleton(string $path): void
{
    File::ensureDirectoryExists($path.'/resources/views/layouts');
    File::ensureDirectoryExists($path.'/resources/views/templates');
    File::put($path.'/resources/views/layouts/app.blade.php', '<html></html>');
    File::put($path.'/resources/views/templates/index.blade.php', '<p>index</p>');
    File::put($path.'/screenshot.png', 'stand-in — the structural check only verifies presence');
}

/**
 * @return list<ThemeViolation>
 */
function validateThemeFixture(array $manifestOverrides = []): array
{
    return app(ThemeValidator::class)->validate(themeManifest($manifestOverrides), themeFixturePath());
}

function hasViolation(array $violations, string $needle, bool $blocking = true): bool
{
    return collect($violations)->contains(
        fn ($v) => str_contains($v->message, $needle) && $v->blocking === $blocking,
    );
}

beforeEach(function () {
    File::deleteDirectory(themeFixturePath());
});

afterEach(function () {
    File::deleteDirectory(themeFixturePath());
});

it('passes a minimal valid theme without violations', function () {
    writeValidThemeSkeleton(themeFixturePath());

    expect(validateThemeFixture())->toBe([]);
});

it('flags a missing root layout as blocking', function () {
    File::ensureDirectoryExists(themeFixturePath().'/resources/views/templates');
    File::put(themeFixturePath().'/resources/views/templates/index.blade.php', '<p>index</p>');
    File::put(themeFixturePath().'/screenshot.png', 'x');

    expect(hasViolation(validateThemeFixture(), 'Layout racine'))->toBeTrue();
});

it('flags a missing screenshot as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::delete(themeFixturePath().'/screenshot.png');

    expect(hasViolation(validateThemeFixture(), 'écran manquante'))->toBeTrue();
});

it('flags a declared parent theme that is not installed as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());

    $violations = validateThemeFixture(['theme' => ['parent' => 'acme/does-not-exist']]);

    expect(hasViolation($violations, 'Thème parent déclaré introuvable'))->toBeTrue();
});

it('flags @php in a blade view as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::put(themeFixturePath().'/resources/views/templates/single.blade.php', "<p>x</p>\n@php \$x = 1; @endphp");

    expect(hasViolation(validateThemeFixture(), 'Directive @php'))->toBeTrue();
});

it('flags a raw <?php tag in a blade view as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::put(themeFixturePath().'/resources/views/templates/single.blade.php', "<p>x</p>\n".'<?php echo 1; ?>');

    expect(hasViolation(validateThemeFixture(), 'Balise PHP brute'))->toBeTrue();
});

it('flags a PHP file inside public/ as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/public');
    File::put(themeFixturePath().'/public/x.php', '<?php ');

    expect(hasViolation(validateThemeFixture(), 'Fichier PHP interdit'))->toBeTrue();
});

it('flags eval() in a PHP file as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\neval('1;');\n");

    expect(hasViolation(validateThemeFixture(), 'eval()'))->toBeTrue();
});

it('flags backtick shell execution as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\n\$out = `ls`;\n");

    expect(hasViolation(validateThemeFixture(), 'backticks'))->toBeTrue();
});

it('flags DB:: static calls as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::table('users')->get();\n");

    expect(hasViolation(validateThemeFixture(), 'DB::'))->toBeTrue();
});

it('flags new PDO() as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\n\$pdo = new PDO('sqlite::memory:');\n");

    expect(hasViolation(validateThemeFixture(), 'new PDO()'))->toBeTrue();
});

it('flags Route:: static calls as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/x', fn () => 1);\n");

    expect(hasViolation(validateThemeFixture(), 'Route::'))->toBeTrue();
});

it('flags Gate::policy() as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nuse Illuminate\\Support\\Facades\\Gate;\nGate::policy('X', 'Y');\n");

    expect(hasViolation(validateThemeFixture(), 'policy/gate'))->toBeTrue();
});

it('flags a curl_* call as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\ncurl_init('https://example.com');\n");

    expect(hasViolation(validateThemeFixture(), 'réseau sortant'))->toBeTrue();
});

it('flags file_get_contents() on a URL as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nfile_get_contents('https://example.com');\n");

    expect(hasViolation(validateThemeFixture(), 'file_get_contents'))->toBeTrue();
});

it('does not flag file_get_contents() on a local path', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nfile_get_contents(__DIR__.'/local.txt');\n");

    expect(validateThemeFixture())->toBe([]);
});

it('flags a Guzzle client instantiation as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\n\$client = new \\GuzzleHttp\\Client();\n");

    expect(hasViolation(validateThemeFixture(), 'Guzzle'))->toBeTrue();
});

it('flags a raw file_put_contents() call as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nfile_put_contents('/tmp/x', 'y');\n");

    expect(hasViolation(validateThemeFixture(), 'Accès fichier bas niveau'))->toBeTrue();
});

it('flags an app container binding as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src');
    File::put(themeFixturePath().'/src/Provider.php', "<?php\nclass P {\n  public function boot(): void {\n    \$this->app->bind('x', fn () => 1);\n  }\n}\n");

    expect(hasViolation(validateThemeFixture(), 'Liaison de service'))->toBeTrue();
});

it('flags an Eloquent model referencing a table outside the theme prefix as blocking', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src/Models');
    File::put(themeFixturePath().'/src/Models/Rogue.php', "<?php\nuse Illuminate\\Database\\Eloquent\\Model;\nclass Rogue extends Model {\n  protected \$table = 'users';\n}\n");

    expect(hasViolation(validateThemeFixture(), 'référençant une table'))->toBeTrue();
});

it('does not flag an Eloquent model referencing a table with the theme prefix', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::ensureDirectoryExists(themeFixturePath().'/src/Models');
    File::put(themeFixturePath().'/src/Models/Setting.php', "<?php\nuse Illuminate\\Database\\Eloquent\\Model;\nclass Setting extends Model {\n  protected \$table = 'validator-fixture_settings';\n}\n");

    expect(validateThemeFixture())->toBe([]);
});

it('warns, without blocking, about a probable Eloquent query in a view', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::put(themeFixturePath().'/resources/views/templates/single.blade.php', "{{ App\\Models\\User::where('id', 1)->first()->name }}");

    $violations = validateThemeFixture();

    expect(hasViolation($violations, 'Eloquent probable', blocking: false))->toBeTrue()
        ->and(collect($violations)->contains(fn ($v) => $v->blocking))->toBeFalse();
});

it('warns, without blocking, about an externally sourced script', function () {
    writeValidThemeSkeleton(themeFixturePath());
    File::put(themeFixturePath().'/resources/views/templates/single.blade.php', '<script src="https://cdn.example.com/lib.js"></script>');

    $violations = validateThemeFixture();

    expect(hasViolation($violations, 'CDN', blocking: false))->toBeTrue()
        ->and(collect($violations)->contains(fn ($v) => $v->blocking))->toBeFalse();
});
