<?php

use Baobab\Install\Actions\ConfigureHashing;
use Baobab\Install\Capability;
use Baobab\Install\DatabaseCredentials;
use Baobab\Install\EnvFile;
use Baobab\Install\InstallationInput;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Users\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Spec 15 §4.1 et §7, suivi n° 220 — argon2id par défaut, bcrypt en repli
 * annoncé.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-hash-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->env = new EnvFile($this->files, $this->repertoire.'/.env');
    $this->files->put($this->repertoire.'/.env', "APP_NAME=Baobab\n");
    $this->connexionInitiale = config('database.default');
    $this->hachageInitial = config('hashing.driver');

    config()->set('baobab.install.state_path', $this->repertoire);
    app()->forgetInstance(InstallationState::class);
    $this->state = app(InstallationState::class);
});

afterEach(function () {
    config()->set('database.default', $this->connexionInitiale);
    config()->set('hashing.driver', $this->hachageInitial);
    DB::purge('sqlite');

    $this->files->deleteDirectory($this->repertoire);
});

it('retient argon2id quand le PHP le propose, et le rend vivant tout de suite', function () {
    $driver = app(ConfigureHashing::class)($this->env, Capability::Present);

    expect($driver)->toBe('argon2id')
        ->and($this->env->get('HASH_DRIVER'))->toBe('argon2id')
        // `.env` est lu au démarrage : l'écrire ne suffit pas pour le
        // processus qui l'écrit (leçon du n° 217).
        ->and(config('hashing.driver'))->toBe('argon2id');
});

it('retombe sur bcrypt quand le PHP ne propose pas argon2id', function (Capability $capacite) {
    $driver = app(ConfigureHashing::class)($this->env, $capacite);

    expect($driver)->toBe('bcrypt')
        ->and($this->env->get('HASH_DRIVER'))->toBe('bcrypt')
        ->and(config('hashing.driver'))->toBe('bcrypt');
})->with([
    'absent' => Capability::Absent,
    'indeterminé' => Capability::Unknown,
]);

/**
 * **Le test qui justifie l'existence de cette Action.** Le compte
 * administrateur naît à l'étape 4 ; si le driver était posé à l'étape 5, avec
 * les autres réglages de site, ce compte-là — et lui seul — serait haché en
 * bcrypt sur un site en argon2id. Un seul compte discordant, celui du Super
 * Admin, et personne pour s'en apercevoir.
 */
it('hache le tout premier compte avec l\'algorithme du site, pas celui d\'avant', function () {
    $entree = new InstallationInput(
        database: new DatabaseCredentials(driver: 'sqlite', database: ':memory:'),
        adminEmail: 'admin@exemple.fr',
        siteName: 'Mon site',
        url: 'https://monsite.fr',
        adminPassword: 'motdepasse-choisi',
        appEnv: 'local',
        version: '1.0.0',
        optimize: false,
    );

    $resume = app(InstallationPipeline::class)(
        $entree,
        $this->env,
        public_path(),
        ['storage' => storage_path()],
    );

    $empreinte = User::query()->where('email', 'admin@exemple.fr')->value('password');

    expect($resume->hashDriver)->toBe('argon2id')
        ->and($empreinte)->toStartWith('$argon2id$')
        ->and(password_get_info($empreinte)['algoName'])->toBe('argon2id');
})->skip(
    fn (): bool => ! in_array('argon2id', password_algos(), true),
    'Ce PHP ne propose pas argon2id.',
);

it('annonce le hachage dans le résumé, pour que la checklist puisse le dire', function () {
    $entree = new InstallationInput(
        database: new DatabaseCredentials(driver: 'sqlite', database: ':memory:'),
        adminEmail: 'admin@exemple.fr',
        siteName: 'Mon site',
        url: 'https://monsite.fr',
        appEnv: 'local',
        version: '1.0.0',
        optimize: false,
    );

    $resume = app(InstallationPipeline::class)($entree, $this->env, public_path(), ['storage' => storage_path()]);

    expect($resume->hashDriver)->toBeIn(['argon2id', 'bcrypt']);
});
