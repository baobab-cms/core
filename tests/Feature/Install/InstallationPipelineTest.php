<?php

use Baobab\Install\DatabaseCredentials;
use Baobab\Install\EnvFile;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Baobab\Install\InstallationInput;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Users\Models\RegistrationSetting;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Spec 15 §1 et §4 — « un seul pipeline, deux interfaces ».
 *
 * Ces tests portent sur l'enchaînement et la reprise, pas sur les Actions :
 * elles ont les leurs. Ce qui est vérifié ici, c'est que la séquence existe
 * à un seul endroit et qu'une installation coupée ne recommence pas de zéro
 * — la reprise était construite depuis la Pass A1 sans que rien ne l'actionne
 * (suivi n° 216).
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-pipeline-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->env = new EnvFile($this->files, $this->repertoire.'/.env');
    $this->files->put($this->repertoire.'/.env', "APP_NAME=Baobab\n");
    // Le conteneur doit rendre UN seul état, partagé par le pipeline et par
    // `FinalizeInstallation` : deux instances écriraient le lock à deux
    // endroits, et le test passerait sur un fichier que personne ne lit.
    config()->set('baobab.install.state_path', $this->repertoire);
    app()->forgetInstance(InstallationState::class);
    $this->state = app(InstallationState::class);
    $this->pipeline = app(InstallationPipeline::class);
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

function entree(): InstallationInput
{
    return new InstallationInput(
        database: new DatabaseCredentials(driver: 'sqlite', database: ':memory:'),
        adminEmail: 'admin@exemple.fr',
        siteName: 'Mon site',
        url: 'https://monsite.fr',
        appEnv: 'local',
        version: '1.0.0',
        optimize: false,
    );
}

function cheminsAccessibles(): array
{
    return ['storage' => storage_path()];
}

it('enchaîne les étapes dans l\x27ordre, et note celles qui ont un effet', function () {
    $vues = [];

    $resume = ($this->pipeline)(entree(), $this->env, public_path(), cheminsAccessibles(), function (string $step) use (&$vues): void {
        $vues[] = $step;
    });

    // `hashing` vient AVANT `account` : le premier compte doit être haché
    // avec l algorithme du site, pas avec celui d avant (n° 220).
    expect($vues)->toBe(['requirements', 'database', 'migrations', 'hashing', 'account', 'site', 'finalization'])
        ->and($resume->wasResumed())->toBeFalse()
        ->and($this->state->isInstalled())->toBeTrue()
        ->and($resume->superAdmin?->user->email)->toBe('admin@exemple.fr')
        ->and($this->env->get('APP_NAME'))->toBe('Mon site')
        ->and(RegistrationSetting::isOpen())->toBeTrue();
});

/**
 * Le cas qui justifie tout le mécanisme : un mutualisé bride le temps
 * d'exécution, et l'étape des migrations est celle qui le dépasse.
 */
it('reprend là où une coupure a laissé l\'installation, sans rejouer ce qui est fait', function () {
    $this->state->recordStep(InstallationPipeline::STEP_DATABASE);
    $this->state->recordStep(InstallationPipeline::STEP_MIGRATIONS);

    $vues = [];
    $resume = ($this->pipeline)(entree(), $this->env, public_path(), cheminsAccessibles(), function (string $step) use (&$vues): void {
        $vues[] = $step;
    });

    expect($vues)->toBe(['requirements', 'hashing', 'account', 'site', 'finalization'])
        ->and($resume->wasResumed())->toBeTrue()
        ->and($resume->skipped)->toBe(['database', 'migrations']);
});

/**
 * L'étape 1 ne modifie rien et coûte quelques millisecondes ; son profil est
 * nécessaire à la finalisation. La noter obligerait à la persister pour la
 * relire, c'est-à-dire à recopier ailleurs ce qu'on peut reprendre.
 */
it('rejoue toujours les prérequis, même sur une reprise avancée', function () {
    foreach (InstallationPipeline::RESUMABLE_STEPS as $etape) {
        $this->state->recordStep($etape);
    }

    $vues = [];
    ($this->pipeline)(entree(), $this->env, public_path(), cheminsAccessibles(), function (string $step) use (&$vues): void {
        $vues[] = $step;
    });

    expect($vues)->toBe(['requirements']);
});

it('s\'arrête sur un prérequis manquant sans avoir rien modifié', function () {
    $avant = $this->files->get($this->repertoire.'/.env');

    expect(fn () => ($this->pipeline)(
        entree(),
        $this->env,
        public_path(),
        ['storage' => $this->repertoire.'/nexiste-pas'],
    ))->toThrow(InstallationStepFailed::class, 'condition(s) nécessaire(s)');

    expect($this->files->get($this->repertoire.'/.env'))->toBe($avant)
        ->and($this->state->isInstalled())->toBeFalse()
        ->and($this->state->completedSteps())->toBe([]);
});

/**
 * Le lock est un fichier qu'on regarde pour diagnostiquer, et qui finit dans
 * une sauvegarde : il ne doit porter aucun secret.
 */
it('ne laisse aucun mot de passe dans le lock', function () {
    $entree = new InstallationInput(
        database: new DatabaseCredentials(driver: 'sqlite', database: ':memory:', password: 'motdepasse-base'),
        adminEmail: 'admin@exemple.fr',
        siteName: 'Mon site',
        url: 'https://monsite.fr',
        adminPassword: 'motdepasse-admin',
        appEnv: 'local',
        version: '1.0.0',
        optimize: false,
    );

    ($this->pipeline)($entree, $this->env, public_path(), cheminsAccessibles());

    $lock = (string) $this->files->get($this->state->lockPath());

    expect($lock)->not->toContain('motdepasse-base')
        ->and($lock)->not->toContain('motdepasse-admin');
});

/**
 * Le test qui manquait, et que seule une question de l'utilisateur a fait
 * écrire : **où les migrations atterrissent-elles ?**
 *
 * Les tests précédents vérifiaient qu'un lock existait et qu'un compte était
 * créé. C'était vrai — mais sur la connexion chargée au démarrage, jamais sur
 * celle que l'utilisateur venait de saisir. `.env` est lu au boot : l'écrire
 * en cours de route ne change rien pour le processus qui l'écrit, et sans
 * bascule explicite l'installation migrait la base de développement de qui
 * lançait la commande.
 *
 * Deux bases, donc, et l'assertion qui compte est la négative.
 */
it('migre la base saisie, et laisse intacte celle qui était configurée', function () {
    $autre = $this->repertoire.'/base-de-developpement.sqlite';
    $cible = $this->repertoire.'/base-cible.sqlite';
    $this->files->put($autre, '');
    $this->files->put($cible, '');

    // La connexion « en place » au démarrage du processus, celle qu'on ne doit
    // surtout pas toucher.
    config()->set('database.connections.developpement', [
        'driver' => 'sqlite',
        'database' => $autre,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    config()->set('database.default', 'developpement');

    $entree = new InstallationInput(
        database: new DatabaseCredentials(driver: 'sqlite', database: $cible),
        adminEmail: 'admin@exemple.fr',
        siteName: 'Mon site',
        url: 'https://monsite.fr',
        appEnv: 'local',
        version: '1.0.0',
        optimize: false,
    );

    ($this->pipeline)($entree, $this->env, public_path(), cheminsAccessibles());

    $tablesCible = tablesDe('sqlite', $cible);
    $tablesAutre = tablesDe('sqlite', $autre);

    expect($tablesCible)->toContain('modules')
        ->and($tablesAutre)->not->toContain('modules')
        ->and($tablesAutre)->toBe([]);
});

/**
 * @return list<string>
 */
function tablesDe(string $driver, string $fichier): array
{
    $nom = 'lecture_'.md5($fichier);

    config()->set('database.connections.'.$nom, [
        'driver' => $driver,
        'database' => $fichier,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge($nom);

    return array_map(
        static fn (array $t): string => $t['name'],
        DB::connection($nom)->getSchemaBuilder()->getTables(),
    );
}
