<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Tests\TestCase;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function fixtureModulesPath(string $path = ''): string
{
    return __DIR__.'/Fixtures/modules'.($path !== '' ? '/'.$path : '');
}

/**
 * Supprime un lien publié par `Baobab\Themes\Actions\PublishThemeAssets`
 * (`public/themes/{slug}`). Ni `is_link()` ni `is_dir()` ne sont fiables
 * ici pour décider entre `unlink()`/`rmdir()` : sur Windows, appeler
 * `is_link()` puis `is_dir()` sur le **même chemin** en séquence retourne
 * un résultat incohérent pour une jonction (`is_dir()` renvoie alors faux,
 * alors qu'appelé seul il renvoie vrai — un bug de cache de stat propre à
 * PHP sur Windows, découvert en écrivant ce helper) ; sur Linux, un
 * symlink vers un répertoire est aussi reconnu par `is_dir()` (il suit le
 * lien), et `rmdir()` dessus échoue (exige une vraie entrée répertoire,
 * pas un lien — erreur POSIX ENOTDIR promue en ErrorException par le
 * handler d'erreurs de Laravel). Plutôt que d'introspecter le système de
 * fichiers, on rejoue la même décision que `Filesystem::link()`
 * (`PublishThemeAssets`) : jonction sur Windows → `rmdir()` ; symlink
 * partout ailleurs → `unlink()`.
 */
function removeThemeLink(string $slug): void
{
    $path = public_path("themes/{$slug}");

    if (! file_exists($path)) {
        return;
    }

    if (windows_os()) {
        rmdir($path);
    } else {
        unlink($path);
    }
}

/**
 * Supprime la jonction publiée par `Baobab\Branding\Support\PublishFontAssets`
 * (`public/baobab/fonts`) et le stockage central du registre de polices
 * (`storage/app/baobab/fonts`) — même rationale que `removeThemeLink()`
 * (jonction unique cette fois, pas une par thème). Utilisé par les tests du
 * registre de polices pour repartir d'un état propre entre les cas.
 */
function resetFontsRegistryStorage(): void
{
    $link = public_path('baobab/fonts');

    if (file_exists($link)) {
        if (windows_os()) {
            rmdir($link);
        } else {
            unlink($link);
        }
    }

    $storage = storage_path('app/baobab/fonts');

    if (is_dir($storage)) {
        (new Filesystem)->deleteDirectory($storage);
    }
}

/**
 * Écrit un faux fichier woff2 (signature binaire correcte, contenu factice
 * au-delà) — suffisant pour `UploadFont`, qui ne valide que les 4 premiers
 * octets (`wOF2`), jamais la structure interne réelle du format.
 */
function createTestWoff2(int $extraBytes = 32): string
{
    $path = sys_get_temp_dir().'/baobab-test-'.bin2hex(random_bytes(6)).'.woff2';
    file_put_contents($path, 'wOF2'.random_bytes($extraBytes));

    return $path;
}

function makeActiveModule(string $name = 'acme/manual'): Module
{
    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Manual\\Providers\\ManualServiceProvider',
        'source' => 'local',
        'path' => '/tmp/'.$name,
        'manifest' => [],
        'status' => 'active',
    ]);
}

/**
 * Creates a user assigned to the given role (for its `level`) and logs them
 * in via the `baobab` guard. Used by hierarchy-related Feature tests.
 */
function actingAsLevel(string $roleName): User
{
    $user = User::create([
        'name' => "Actor ({$roleName})",
        'email' => strtolower($roleName).'-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));

    test()->actingAs($user, 'baobab');

    return $user;
}

/**
 * A user assigned to the given role, granted baobab.admin.access and
 * baobab.users.impersonate directly. Used as the acting party in
 * ImpersonationTest.php.
 */
function impersonationActor(string $roleName): User
{
    $user = User::create([
        'name' => "Actor ({$roleName})",
        'email' => strtolower($roleName).'-actor-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.users.impersonate');

    return $user;
}

/**
 * A user assigned to the given role, with no permissions granted — the
 * impersonation target in ImpersonationTest.php.
 */
function impersonationTarget(string $roleName): User
{
    $user = User::create([
        'name' => "Target ({$roleName})",
        'email' => strtolower($roleName).'-target-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));

    return $user;
}

/**
 * Path of today's baobab technical log file (daily channel, spec 12 §9).
 * Used by LoggerTest.php.
 */
function baobabLogPath(): string
{
    return storage_path('logs/baobab-'.now()->format('Y-m-d').'.log');
}

/**
 * A minimal valid Content Type blueprint (JSON), key "Car" by default.
 * Used by ContentTypeBlueprintTest.php and CreateContentTypeTest.php.
 *
 * @param  array<string, mixed>  $overrides
 */
function carBlueprintJson(array $overrides = []): string
{
    return (string) json_encode(array_replace([
        'key' => 'Car',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
    ], $overrides));
}

/**
 * Répertoire temporaire cible du générateur de Content Types dans les tests
 * (config baobab.content_types.modules_path). Utilisé par
 * ContentTypeModuleGeneratorTest.php et BuildContentTypeTest.php.
 */
function generatedModulesPath(): string
{
    return sys_get_temp_dir().'/baobab-test-content-type-modules';
}

/**
 * Écrit un JPEG minimal (via GD) sur disque et retourne son chemin absolu.
 * Utilisé par UploadMediaTest.php.
 */
function createTestJpeg(int $width = 20, int $height = 10): string
{
    $path = sys_get_temp_dir().'/baobab-test-'.bin2hex(random_bytes(6)).'.jpg';
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 50, 50));
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

/**
 * Écrit un JPEG large (défaut 200x100) coupé en deux couleurs franches
 * (gauche rouge, droite bleue) — sert à vérifier qu'un recadrage `fit: crop`
 * pilote réellement par le point focal et pas juste par un centrage fixe :
 * un point focal à gauche doit produire un recadré majoritairement rouge, un
 * point focal à droite majoritairement bleu. Utilisé par
 * GenerateMediaConversionsTest.php.
 */
function createSplitColorTestJpeg(int $width = 200, int $height = 100): string
{
    $path = sys_get_temp_dir().'/baobab-test-split-'.bin2hex(random_bytes(6)).'.jpg';
    $image = imagecreatetruecolor($width, $height);
    $red = (int) imagecolorallocate($image, 220, 20, 20);
    $blue = (int) imagecolorallocate($image, 20, 20, 220);
    imagefilledrectangle($image, 0, 0, (int) ($width / 2) - 1, $height - 1, $red);
    imagefilledrectangle($image, (int) ($width / 2), 0, $width - 1, $height - 1, $blue);
    imagejpeg($image, $path, 95);
    imagedestroy($image);

    return $path;
}

/**
 * Écrit un JPEG minimal contenant un segment EXIF (TIFF) fait main —
 * orientation + un tag GPS minimal (GPSVersionID, ne nécessite pas de bloc
 * de données externe) — pas de bibliothèque de test capable d'écrire de
 * l'EXIF, donc construit octet par octet. Utilisé par UploadMediaTest.php
 * pour prouver que ExifNormalizer supprime réellement le GPS et applique
 * l'orientation, pas seulement en théorie.
 */
function createTestJpegWithExif(int $orientation, int $width = 20, int $height = 10): string
{
    $jpegPath = createTestJpeg($width, $height);
    $jpegBytes = (string) file_get_contents($jpegPath);

    // IFD0 : Orientation (tag 0x0112, SHORT) + pointeur vers l'IFD GPS (tag 0x8825, LONG).
    $ifd0 = pack('v', 2)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
        .pack('v', 0x8825).pack('v', 4).pack('V', 1).pack('V', 38)
        .pack('V', 0);

    // IFD GPS à l'offset 38 (8 octets d'en-tête TIFF + 30 octets d'IFD0) : GPSVersionID seul,
    // suffisant pour qu'exif_read_data() rapporte une clé GPS sans bloc de données externe.
    $gpsIfd = pack('v', 1)
        .pack('v', 0x0000).pack('v', 1).pack('V', 4)."\x02\x03\x00\x00"
        .pack('V', 0);

    $tiff = 'II'.pack('v', 42).pack('V', 8).$ifd0.$gpsIfd;
    $app1Length = 2 + 6 + strlen($tiff);
    $app1 = "\xFF\xE1".pack('n', $app1Length)."Exif\x00\x00".$tiff;

    // Insère l'APP1/Exif juste après le marqueur SOI (2 premiers octets), avant le reste du JPEG.
    file_put_contents($jpegPath, substr($jpegBytes, 0, 2).$app1.substr($jpegBytes, 2));

    return $jpegPath;
}

/**
 * Content Type "ApiCar" (addressable, brand/price/internal_note dont
 * internal_note n'est pas exposé en API) utilisé par les tests REST
 * (lecture et écriture, `tests/Feature/Api/`).
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildApiCar(array $overrides = []): array
{
    $contentType = app(BuildContentType::class)((string) json_encode(array_replace([
        'key' => 'ApiCar',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'price', 'type' => 'decimal'],
            ['key' => 'internal_note', 'type' => 'text', 'exposed_in_api' => false],
        ],
    ], $overrides)));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    $fresh = $contentType->fresh();

    if ($fresh === null) {
        throw new RuntimeException('Expected the newly built ApiCar content type to be refetchable.');
    }

    return [$fresh, $modelClass];
}

/**
 * Content Type "ApiManufacturer" — cible de relation pour les tests
 * `?include=` des tests REST.
 *
 * @return class-string<Model>
 */
function buildApiManufacturer(): string
{
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'ApiManufacturer',
        'label' => ['singular' => 'Fabricant', 'plural' => 'Fabricants'],
        'fields' => [
            ['key' => 'name', 'type' => 'text', 'required' => true],
        ],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return $modelClass;
}

/**
 * Utilisateur sans rôle, avec seulement les permissions données — acteur des
 * tests REST (`tests/Feature/Api/`).
 *
 * @param  list<string>  $permissions
 */
function apiActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "API Actor {$counter}",
        'email' => "api-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * POST une requête GraphQL vers `/graphql`. Utilisé par les tests GraphQL
 * (`tests/Feature/Api/`).
 *
 * @param  array<string, mixed>  $variables
 * @return TestResponse<JsonResponse>
 */
function graphqlQuery(string $query, array $variables = []): TestResponse
{
    return test()->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
}
