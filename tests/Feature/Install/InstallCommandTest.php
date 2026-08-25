<?php

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Install\WebServer;
use Baobab\Users\Models\RegistrationSetting;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Spec 15 §5 — `baobab:install`, l'adaptateur console.
 *
 * Adaptateur mince : ce qui se teste ici est la traduction des options en
 * `InstallationInput` et les gardes, pas la séquence — elle a ses propres
 * tests (`InstallationPipelineTest`), et elle n'existe qu'à un seul endroit.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-cli-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->files->put($this->repertoire.'/.env.example', "APP_NAME=Baobab\nAPP_URL=http://localhost\n");

    config()->set('baobab.install.state_path', $this->repertoire);
    config()->set('baobab.install.env_path', $this->repertoire.'/.env');
    // Un `config:cache` écrit un fichier qui survit à la suite et empoisonne
    // tout ce qui suit, exécutions ultérieures comprises.
    config()->set('baobab.install.optimize', false);
    // SQLite n est accepté qu en local ou préproduction (§8, décision 3), et
    // Testbench tourne en `testing` : la garde ferait échouer l installation,
    // à juste titre. On installe donc comme on installerait en local.
    config()->set('app.env', 'local');
    app()->forgetInstance(InstallationState::class);
    $this->state = app(InstallationState::class);
    $this->connexionInitiale = config('database.default');
});

/**
 * `ConfigureDatabase` bascule la connexion par défaut : c'est son travail, et
 * un test qui l'exerce doit défaire ce qu'il a provoqué **avant** de supprimer
 * les fichiers — sinon l'application reste pointée sur une base disparue, et
 * c'est le test suivant qui tombe.
 *
 * Windows masquait le défaut en refusant de supprimer un fichier SQLite encore
 * ouvert ; Linux le supprime, et la CI l'a dit. Seule la connexion créée par
 * l'Action est purgée : purger celle du banc d'essai détruirait sa base
 * **en mémoire**, que `RefreshDatabase` ne remigrerait pas.
 */
afterEach(function () {
    config()->set('database.default', $this->connexionInitiale);
    DB::purge('sqlite');

    $this->files->deleteDirectory($this->repertoire);
});

it('refuse de réinstaller un site déjà installé', function () {
    $this->state->markInstalled('1.0.0', new HostingProfile(Capability::Present, Capability::Present, Capability::Present, Capability::Unknown, WebServer::Unknown), 'somme');

    $this->artisan('baobab:install', ['--no-interaction' => true])
        ->assertExitCode(1)
        ->expectsOutputToContain('déjà installé');
});

/**
 * Le mode non interactif sert la CI et le provisioning d'agence : il ne doit
 * jamais attendre devant une invite, donc il exige ce qu'il ne peut pas
 * deviner plutôt que de le demander.
 */
it('exige ce qu\'il ne peut pas deviner plutôt que de bloquer sur une invite', function () {
    $this->artisan('baobab:install', ['--no-interaction' => true])
        ->assertExitCode(1)
        ->expectsOutputToContain('--db-connection est requis');
});

it('installe de bout en bout sans poser une seule question', function () {
    $this->artisan('baobab:install', [
        '--no-interaction' => true,
        '--db-connection' => 'sqlite',
        '--db-database' => ':memory:',
        '--admin-email' => 'admin@exemple.fr',
        '--admin-name' => 'Privat',
        '--site-name' => 'Mon site',
        '--url' => 'https://monsite.fr',
        '--closed-registration' => true,
    ])->assertExitCode(0);

    expect($this->state->isInstalled())->toBeTrue()
        ->and(User::query()->where('email', 'admin@exemple.fr')->exists())->toBeTrue()
        ->and(RegistrationSetting::isOpen())->toBeFalse()
        ->and($this->files->get($this->repertoire.'/.env'))->toContain('APP_URL=https://monsite.fr');
});

/**
 * Un mot de passe passé en argument fuiterait dans l'historique du shell et
 * dans la liste des processus, où n'importe quel utilisateur de la machine le
 * lirait. La commande ne l'accepte donc que par variable d'environnement.
 */
it('ne prend le mot de passe que par variable d\'environnement', function () {
    putenv('BAOBAB_TEST_ADMIN_PASSWORD=motdepasse-choisi');

    $this->artisan('baobab:install', [
        '--no-interaction' => true,
        '--db-connection' => 'sqlite',
        '--db-database' => ':memory:',
        '--admin-email' => 'admin@exemple.fr',
        '--admin-password-env' => 'BAOBAB_TEST_ADMIN_PASSWORD',
        '--site-name' => 'Mon site',
        '--url' => 'https://monsite.fr',
    ])->assertExitCode(0);

    putenv('BAOBAB_TEST_ADMIN_PASSWORD');

    // Aucune option ne porte le mot de passe lui-même.
    $definition = $this->app->make(Kernel::class)
        ->all()['baobab:install']->getDefinition();

    expect($definition->hasOption('admin-password'))->toBeFalse()
        ->and($definition->hasOption('db-password'))->toBeFalse()
        ->and(auth('baobab')->attempt(['email' => 'admin@exemple.fr', 'password' => 'motdepasse-choisi']))->toBeTrue();
});

it('reprend une installation coupée sans rejouer ce qui est fait', function () {
    $this->state->recordStep(InstallationPipeline::STEP_DATABASE);
    $this->state->recordStep(InstallationPipeline::STEP_MIGRATIONS);

    $this->artisan('baobab:install', [
        '--no-interaction' => true,
        '--db-connection' => 'sqlite',
        '--db-database' => ':memory:',
        '--admin-email' => 'admin@exemple.fr',
        '--site-name' => 'Mon site',
        '--url' => 'https://monsite.fr',
    ])->assertExitCode(0);

    expect($this->state->isInstalled())->toBeTrue();
});
