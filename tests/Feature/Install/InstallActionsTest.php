<?php

use Baobab\Install\Actions\ConfigureDatabase;
use Baobab\Install\Actions\ConfigureSite;
use Baobab\Install\Actions\CreateSuperAdmin;
use Baobab\Install\Actions\FinalizeInstallation;
use Baobab\Install\Capability;
use Baobab\Install\DatabaseCredentials;
use Baobab\Install\EnvFile;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Baobab\Install\WebServer;
use Baobab\Users\Models\RegistrationSetting;
use Baobab\Users\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Spec 15 §4, étapes 2 à 6 — les cinq Actions de la Pass A2.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-a2-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->env = new EnvFile($this->files, $this->repertoire.'/.env');
    $this->files->put($this->repertoire.'/.env', "APP_NAME=Baobab\nAPP_URL=http://localhost\n");
    $this->connexionInitiale = config('database.default');
});

/**
 * `ConfigureDatabase` bascule la connexion par défaut : c'est son travail, et
 * un test qui l'exerce doit défaire ce qu'il a provoqué **avant** de supprimer
 * les fichiers, sinon l'application reste pointée sur une base qui n'existe
 * plus.
 *
 * Windows masquait le défaut en refusant de supprimer un fichier SQLite encore
 * ouvert ; Linux le supprime, et la CI l'a dit. Le test était fragile sur les
 * deux, visible sur un seul.
 */
afterEach(function () {
    config()->set('database.default', $this->connexionInitiale);
    // Seule la connexion créée par l Action est purgée. Purger celle du
    // banc d essai détruirait sa base SQLite **en mémoire**, que
    // `RefreshDatabase` ne remigrerait pas : les tests suivants
    // tourneraient sur une base vide.
    DB::purge('sqlite');

    $this->files->deleteDirectory($this->repertoire);
});

function profilComplet(): HostingProfile
{
    return new HostingProfile(
        symlink: Capability::Present,
        procOpen: Capability::Absent,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Unknown,
        webServer: WebServer::Apache,
    );
}

// ── Étape 2 ────────────────────────────────────────────────────────────────

it('refuse SQLite en production, y compris sans passer par un sélecteur', function () {
    $action = app(ConfigureDatabase::class);

    expect(fn () => $action(
        new DatabaseCredentials(driver: 'sqlite', database: ':memory:'),
        $this->env,
        'production',
    ))->toThrow(InstallationStepFailed::class, 'SQLite ne convient pas');
})->note('Spec 15 §8, décision 3 — l\'interdit vaut aussi pour --no-interaction.');

it('accepte SQLite en préproduction', function () {
    $action = app(ConfigureDatabase::class);

    $inspection = $action(
        new DatabaseCredentials(driver: 'sqlite', database: ':memory:'),
        $this->env,
        'staging',
    );

    expect($inspection->isEmpty())->toBeTrue()
        ->and($this->env->get('DB_CONNECTION'))->toBe('sqlite');
});

it('n\'écrit rien dans .env quand la connexion échoue', function () {
    $action = app(ConfigureDatabase::class);
    $avant = $this->files->get($this->repertoire.'/.env');

    expect(fn () => $action(
        new DatabaseCredentials(driver: 'mysql', database: 'absente', host: '127.0.0.1', port: 1, username: 'x', password: 'y'),
        $this->env,
        'local',
    ))->toThrow(InstallationStepFailed::class);

    // Le fond de l'étape : écrire d'abord et vérifier ensuite laisserait, à la
    // moindre faute de frappe, une application qui ne démarre plus — et
    // l'installateur avec elle, puisqu'il tourne dedans.
    expect($this->files->get($this->repertoire.'/.env'))->toBe($avant);
});

// ── Étape 4 ────────────────────────────────────────────────────────────────

it('crée le premier compte et lui forge un mot de passe', function () {
    $result = app(CreateSuperAdmin::class)('admin@exemple.fr');

    expect($result->created)->toBeTrue()
        ->and($result->generatedPassword)->toBeString()
        ->and(mb_strlen($result->generatedPassword))->toBe(16)
        ->and($result->user->hasRole(CreateSuperAdmin::ROLE))->toBeTrue();
});

it('reste idempotente sur un e-mail déjà connu, sans toucher au mot de passe', function () {
    $action = app(CreateSuperAdmin::class);

    $premier = $action('admin@exemple.fr', 'Privat', 'motdepasse-choisi');
    $empreinte = $premier->user->fresh()->password;

    $second = $action('admin@exemple.fr');

    expect($second->created)->toBeFalse()
        ->and($second->generatedPassword)->toBeNull()
        ->and($second->user->fresh()->password)->toBe($empreinte)
        ->and(User::query()->where('email', 'admin@exemple.fr')->count())->toBe(1);
})->note('C\'est ce qui permet à baobab:super-admin de servir de secours sur une instance vivante.');

// ── Étape 5 ────────────────────────────────────────────────────────────────

it('range l\'identité du site où chacun la lira — .env pour Laravel, la base pour l\'admin', function () {
    app(ConfigureSite::class)($this->env, 'Mon site', 'https://monsite.fr/', 'Europe/Paris', false);

    expect($this->env->get('APP_NAME'))->toBe('Mon site')
        ->and($this->env->get('APP_URL'))->toBe('https://monsite.fr')
        ->and($this->env->get('APP_TIMEZONE'))->toBe('Europe/Paris')
        ->and(RegistrationSetting::isOpen())->toBeFalse();
})->note('Suivi n° 214 — pas de seconde vérité sur le nom du site.');

it('laisse l\'inscription ouverte par défaut, comme la spec 05 le décide', function () {
    app(ConfigureSite::class)($this->env, 'Mon site', 'https://monsite.fr');

    expect(RegistrationSetting::isOpen())->toBeTrue();
});

// ── Étape 6 ────────────────────────────────────────────────────────────────

it('écrit le lock en dernier, avec le profil réellement constaté', function () {
    $state = new InstallationState($this->files, $this->repertoire);

    $profil = app(FinalizeInstallation::class, ['state' => $state])('1.0.0', profilComplet(), 'somme', optimize: false);

    expect($state->isInstalled())->toBeTrue()
        ->and($state->lock()['version'])->toBe('1.0.0')
        ->and($profil->procOpen)->toBe(Capability::Absent);
});

it('efface l\'avancement en finalisant, puisqu\'il n\'a plus rien à reprendre', function () {
    $state = new InstallationState($this->files, $this->repertoire);
    $state->recordStep('database');

    app(FinalizeInstallation::class, ['state' => $state])('1.0.0', profilComplet(), 'somme', optimize: false);

    expect($state->completedSteps())->toBe([]);
});

/**
 * Le lock du §3 ne suffit pas : `storage/` est couramment exclu des
 * sauvegardes, ou vidé lors d'un changement d'hébergement. Un lock perdu
 * ferait croire à l'installateur qu'il est sur un terrain neuf — et il
 * migrerait par-dessus un site vivant. La base, elle, ne ment pas.
 */
it('refuse d\'installer par-dessus un Baobab qui vit déjà dans cette base', function () {
    $fichier = $this->repertoire.'/site-en-production.sqlite';
    $this->files->put($fichier, '');

    config()->set('database.connections.existante', [
        'driver' => 'sqlite',
        'database' => $fichier,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::connection('existante')->getSchemaBuilder()->create('modules', function ($table) {
        $table->id();
    });

    $action = app(ConfigureDatabase::class);
    $avant = $this->files->get($this->repertoire.'/.env');

    expect(fn () => $action(
        new DatabaseCredentials(driver: 'sqlite', database: $fichier),
        $this->env,
        'local',
    ))->toThrow(InstallationStepFailed::class, 'contient déjà un site Baobab');

    // « rien n'a été modifié » n'est pas une formule : le .env doit être intact.
    expect($this->files->get($this->repertoire.'/.env'))->toBe($avant);
});

/**
 * Le préfixe est justement la réponse à une base occupée : des tables Baobab
 * sous un autre préfixe ne sont pas les nôtres, et ne doivent rien bloquer.
 */
it('n\'est pas gêné par un Baobab voisin installé sous un autre préfixe', function () {
    $fichier = $this->repertoire.'/base-partagee.sqlite';
    $this->files->put($fichier, '');

    config()->set('database.connections.voisine', [
        'driver' => 'sqlite',
        'database' => $fichier,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::connection('voisine')->getSchemaBuilder()->create('voisin_modules', function ($table) {
        $table->id();
    });

    $inspection = app(ConfigureDatabase::class)(
        new DatabaseCredentials(driver: 'sqlite', database: $fichier, prefix: 'monsite_'),
        $this->env,
        'local',
    );

    expect($inspection->isEmpty())->toBeFalse()
        ->and($inspection->conflicts())->toBe([]);
});
