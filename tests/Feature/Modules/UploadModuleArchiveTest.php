<?php

use Baobab\Actions\Modules\UploadModuleArchive;
use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\InvalidManifestException;
use Baobab\Modules\Exceptions\InvalidModuleArchiveException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleDiscovery;
use Illuminate\Support\Facades\File;

function uploadRoot(): string
{
    return sys_get_temp_dir().'/baobab-test-module-upload';
}

function uploadModulesPath(): string
{
    return uploadRoot().'/modules';
}

function uploadThemesPath(): string
{
    return uploadRoot().'/themes';
}

beforeEach(function () {
    File::deleteDirectory(uploadRoot());
    File::ensureDirectoryExists(uploadModulesPath());
    File::ensureDirectoryExists(uploadThemesPath());

    config([
        'baobab.modules.upload.modules_path' => uploadModulesPath(),
        'baobab.modules.upload.themes_path' => uploadThemesPath(),
        'baobab.modules.upload.max_size' => 5 * 1024 * 1024,
        'baobab.modules.paths' => ['local' => [uploadModulesPath().'/*', uploadThemesPath().'/*']],
    ]);
});

afterEach(function () {
    File::deleteDirectory(uploadRoot());
});

/**
 * @return array<string, mixed>
 */
function uploadManifest(array $overrides = []): array
{
    return [
        'name' => 'acme/uploaded',
        'title' => 'Uploaded',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Uploaded\\UploadedServiceProvider',
        ...$overrides,
    ];
}

/**
 * @param  array<string, string>  $files  Chemin dans l'archive → contenu.
 */
function makeZip(array $files, ?callable $tweak = null): string
{
    $path = uploadRoot().'/archive-'.bin2hex(random_bytes(4)).'.zip';
    File::ensureDirectoryExists(dirname($path));

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $entry => $contents) {
        $zip->addFromString($entry, $contents);
    }

    if ($tweak !== null) {
        $tweak($zip);
    }

    $zip->close();

    return $path;
}

/**
 * @param  array<string, mixed>  $manifestOverrides
 * @param  array<string, string>  $extraFiles
 */
function makeModuleZip(array $manifestOverrides = [], array $extraFiles = [], string $prefix = ''): string
{
    return makeZip([
        $prefix.'module.json' => (string) json_encode(uploadManifest($manifestOverrides)),
        $prefix.'src/Providers/UploadedServiceProvider.php' => "<?php\n// provider",
        ...$extraFiles,
    ]);
}

it('drops a module archive where discovery will find it, without installing it', function () {
    $received = null;
    Hook::listen('baobab.module.uploaded', function (string $name) use (&$received) {
        $received = $name;
    });

    $name = app(UploadModuleArchive::class)(makeModuleZip());

    expect($name)->toBe('acme/uploaded')
        ->and(File::isFile(uploadModulesPath().'/acme-uploaded/module.json'))->toBeTrue()
        ->and(File::isFile(uploadModulesPath().'/acme-uploaded/src/Providers/UploadedServiceProvider.php'))->toBeTrue()
        ->and($received)->toBe('acme/uploaded');

    // Déposé, pas installé : la spec §4 décrit un état « téléchargé » où le
    // code est présent mais inerte.
    expect(Module::where('name', 'acme/uploaded')->exists())->toBeFalse()
        ->and(app(ModuleDiscovery::class)->scan()->has('acme/uploaded'))->toBeTrue();
});

it('routes a theme archive to the themes directory, per its declared type', function () {
    app(UploadModuleArchive::class)(makeModuleZip([
        'type' => 'theme',
        // Le schéma exige le bloc `theme` dès que le type l'est (spec 03 §2.1).
        'theme' => ['parent' => null, 'menus' => ['primary' => 'Principal'], 'widget_zones' => ['sidebar' => 'Latérale']],
    ]));

    expect(File::isFile(uploadThemesPath().'/acme-uploaded/module.json'))->toBeTrue()
        ->and(File::exists(uploadModulesPath().'/acme-uploaded'))->toBeFalse();
});

/**
 * Nos propres archives mettent les fichiers à la racine ; celles téléchargées
 * depuis une forge les enveloppent dans un dossier. Les deux sont acceptées,
 * et l'enveloppe est retirée.
 */
it('unwraps an archive whose module sits under a single top-level folder', function () {
    app(UploadModuleArchive::class)(makeModuleZip(prefix: 'acme-uploaded-main/'));

    expect(File::isFile(uploadModulesPath().'/acme-uploaded/module.json'))->toBeTrue()
        ->and(File::exists(uploadModulesPath().'/acme-uploaded/acme-uploaded-main'))->toBeFalse();
});

// --- Refus de forme ---------------------------------------------------------

it('refuses a file that is not a zip, whatever its name says', function () {
    $path = uploadRoot().'/not-really.zip';
    File::put($path, 'je ne suis pas une archive');

    app(UploadModuleArchive::class)($path);
})->throws(InvalidModuleArchiveException::class, "n'est pas une archive ZIP");

it('refuses an archive without any module.json', function () {
    app(UploadModuleArchive::class)(makeZip(['README.md' => '# rien ici']));
})->throws(InvalidModuleArchiveException::class, 'aucun module.json');

it('refuses an archive describing two modules at once', function () {
    app(UploadModuleArchive::class)(makeZip([
        'first/module.json' => (string) json_encode(uploadManifest()),
        'second/module.json' => (string) json_encode(uploadManifest(['name' => 'acme/other'])),
    ]));
})->throws(InvalidModuleArchiveException::class, 'plusieurs module.json');

it('refuses an archive larger than the configured maximum', function () {
    config(['baobab.modules.upload.max_size' => 10]);

    app(UploadModuleArchive::class)(makeModuleZip());
})->throws(InvalidModuleArchiveException::class, 'taille maximale');

it('refuses an archive whose manifest does not satisfy the schema, writing nothing', function () {
    $path = makeZip(['module.json' => (string) json_encode(['name' => 'acme/uploaded'])]);

    try {
        app(UploadModuleArchive::class)($path);
    } catch (InvalidManifestException) {
        // Le point du test est ce qui suit : rien n'a été déposé.
    }

    expect(File::exists(uploadModulesPath().'/acme-uploaded'))->toBeFalse();
});

// --- Refus de sécurité ------------------------------------------------------

/**
 * Zip Slip : une entrée qui remonte hors du dossier d'extraction écrirait
 * n'importe où sur le serveur. L'archive entière est refusée, et rien n'est
 * déposé — pas même les entrées saines qui la précèdent.
 */
it('refuses an archive containing a path traversal, and writes nothing at all', function () {
    $path = makeModuleZip(extraFiles: ['../../evil.php' => '<?php // pwn']);

    try {
        app(UploadModuleArchive::class)($path);
        expect(false)->toBeTrue('Le zip slip aurait dû être refusé.');
    } catch (InvalidModuleArchiveException $exception) {
        expect($exception->getMessage())->toContain('chemin non sûr');
    }

    expect(File::exists(uploadModulesPath().'/acme-uploaded'))->toBeFalse()
        ->and(File::exists(uploadRoot().'/evil.php'))->toBeFalse()
        ->and(File::exists(dirname(uploadRoot()).'/evil.php'))->toBeFalse();
});

it('refuses an absolute POSIX path', function () {
    app(UploadModuleArchive::class)(makeModuleZip(extraFiles: ['/etc/passwd' => 'root']));
})->throws(InvalidModuleArchiveException::class, 'chemin non sûr');

it('refuses an absolute Windows path with a drive letter', function () {
    app(UploadModuleArchive::class)(makeModuleZip(extraFiles: ['C:/windows/system32/evil.dll' => 'x']));
})->throws(InvalidModuleArchiveException::class, 'chemin non sûr');

it('refuses a backslash traversal, which Windows would resolve', function () {
    app(UploadModuleArchive::class)(makeModuleZip(extraFiles: ['..\\..\\evil.php' => '<?php']));
})->throws(InvalidModuleArchiveException::class, 'chemin non sûr');

/**
 * Un lien symbolique dans l'archive ferait pointer un fichier du module vers
 * n'importe quoi sur le serveur — un `.env` lu par un template, par exemple.
 */
it('refuses a symlink entry', function () {
    $path = makeModuleZip(extraFiles: ['config.php' => '/etc/passwd']);

    $zip = new ZipArchive;
    $zip->open($path);
    $index = $zip->locateName('config.php');
    // S_IFLNK (0xA000) | 0777, dans les 16 bits de poids fort du mode UNIX.
    $zip->setExternalAttributesIndex((int) $index, ZipArchive::OPSYS_UNIX, (0xA1FF) << 16);
    $zip->close();

    app(UploadModuleArchive::class)($path);
})->throws(InvalidModuleArchiveException::class, 'chemin non sûr');

// --- Refus d'écrasement -----------------------------------------------------

it('refuses to overwrite a module that is already installed', function () {
    Module::create([
        'name' => 'acme/uploaded',
        'title' => 'Uploaded',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Uploaded\\UploadedServiceProvider',
        'source' => 'local',
        'path' => uploadModulesPath().'/acme-uploaded',
        'manifest' => uploadManifest(),
        'status' => 'installed',
    ]);

    app(UploadModuleArchive::class)(makeModuleZip());
})->throws(InvalidModuleArchiveException::class, 'existe déjà');

/**
 * Un dossier présent sans ligne `modules` est le travail de quelqu'un — un
 * module déposé à la main, jamais installé. On ne l'écrase pas davantage.
 */
it('refuses to overwrite a directory that exists without being installed', function () {
    File::ensureDirectoryExists(uploadModulesPath().'/acme-uploaded');
    File::put(uploadModulesPath().'/acme-uploaded/marqueur.txt', 'travail de quelqu\'un');

    try {
        app(UploadModuleArchive::class)(makeModuleZip());
        expect(false)->toBeTrue('Le dossier occupé aurait dû être refusé.');
    } catch (InvalidModuleArchiveException $exception) {
        expect($exception->getMessage())->toContain('existe déjà sur le serveur');
    }

    expect(File::get(uploadModulesPath().'/acme-uploaded/marqueur.txt'))->toBe('travail de quelqu\'un');
});
