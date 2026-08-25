<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 15 §4, étape 2 — le préfixe de tables, éprouvé plutôt que supposé.
 *
 * **C'est la condition posée à la décision du n° 214.** Un mutualisé ne donne
 * souvent qu'une seule base, et la spec y répond par un préfixe. La revue du
 * Core disait que ça devait marcher — aucun SQL brut, tout en Schema et
 * Eloquent, qui honorent `DB_PREFIX` nativement. Mais « devrait marcher » et
 * « marche » sont deux choses, et promettre un préfixe à l'installation, c'est
 * le promettre pour toute la vie du site. D'où ces tests, qui exercent le
 * chemin complet : les migrations du Core, puis un Content Type généré.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->fichier = sys_get_temp_dir().'/baobab-prefix-'.bin2hex(random_bytes(6)).'.sqlite';
    $this->files->put($this->fichier, '');

    config()->set('database.connections.prefixe', [
        'driver' => 'sqlite',
        'database' => $this->fichier,
        'prefix' => 'monsite_',
        'foreign_key_constraints' => false,
    ]);
});

afterEach(function () {
    DB::purge('prefixe');
    $this->files->delete($this->fichier);
});

/**
 * @return list<string>
 */
function tablesPhysiques(string $connexion): array
{
    return array_map(
        static fn (array|object $t): string => (string) (is_array($t) ? $t['name'] : $t->name),
        DB::connection($connexion)->getSchemaBuilder()->getTables(),
    );
}

it('joue toutes les migrations du Core sous préfixe, et crée les tables préfixées', function () {
    Artisan::call('migrate', ['--database' => 'prefixe', '--force' => true]);

    $tables = tablesPhysiques('prefixe');

    // Les noms physiques portent le préfixe...
    expect($tables)->toContain('monsite_modules')
        ->and($tables)->toContain('monsite_content_types')
        ->and($tables)->toContain('monsite_registration_settings')
        // ...et rien n'a été créé sans lui, hors tables techniques de SQLite.
        ->and($tables)->not->toContain('modules')
        ->and($tables)->not->toContain('content_types');
});

it('laisse Schema et Eloquent parler en noms logiques, le préfixe restant invisible au code', function () {
    Artisan::call('migrate', ['--database' => 'prefixe', '--force' => true]);

    // Tout le Core interroge des noms logiques : c'est la condition pour que
    // le préfixe ne demande aucune modification ailleurs.
    expect(Schema::connection('prefixe')->hasTable('modules'))->toBeTrue()
        ->and(Schema::connection('prefixe')->hasColumn('registration_settings', 'open'))->toBeTrue();

    DB::connection('prefixe')->table('registration_settings')->insert([
        'id' => 1,
        'open' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::connection('prefixe')->table('registration_settings')->count())->toBe(1);
});

/**
 * Le cas qui comptait vraiment : le Studio écrit une migration et un modèle
 * Eloquent pour chaque Content Type. Si le préfixe cassait quelque part, ce
 * serait là — sur une table qui n'existe pas encore au moment où le code est
 * écrit.
 *
 * La base préfixée devient ici la connexion **par défaut**, ce qui est la
 * seule façon honnête de l'éprouver : c'est la situation d'un site installé
 * avec un préfixe, où plus rien ensuite ne sait qu'il y en a un.
 */
it('génère un Content Type dont la table naît préfixée, et le relit par son modèle', function () {
    Artisan::call('migrate', ['--database' => 'prefixe', '--force' => true]);

    $defautInitial = config('database.default');
    config()->set('database.default', 'prefixe');
    config()->set('baobab.studio.modules_path', generatedModulesPath());
    config()->set('baobab.content_types.modules_path', generatedModulesPath());
    config()->set('baobab.modules.paths', ['local' => [generatedModulesPath().'/*']]);
    $this->files->deleteDirectory(generatedModulesPath());

    try {
        $contentType = app(BuildContentType::class)((string) json_encode([
            'key' => 'PrefixedShelf',
            'label' => ['singular' => 'Étagère', 'plural' => 'Étagères'],
            'is_addressable' => false,
            'title_field' => 'label',
            'fields' => [
                ['key' => 'label', 'type' => 'text', 'required' => true],
            ],
        ]));

        app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();
        $entree = $modelClass::create(['label' => 'Rayon du fond', 'status' => 'draft']);

        expect(tablesPhysiques('prefixe'))->toContain('monsite_ct_prefixed_shelves')
            ->and($modelClass::query()->find($entree->getKey()))->not->toBeNull();
    } finally {
        config()->set('database.default', $defautInitial);
        $this->files->deleteDirectory(generatedModulesPath());
    }
});
