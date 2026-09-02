<?php

use Baobab\Install\DatabaseCredentials;
use Baobab\Install\EnvFile;
use Baobab\Install\InstallationInput;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallDraft;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Spec 15 §6.1 — l'installation conduite **une étape par requête** (Pass C2a,
 * suivi n° 224).
 *
 * Le wizard ne peut pas porter toute la séquence dans une requête : `migrate`
 * est l'étape longue, et un hébergement mutualisé tue ce qui dépasse. Ce que
 * ces tests éprouvent, c'est donc qu'`advance()` avance **d'un cran**, dans
 * l'ordre, et sache dire quand il n'y a plus rien à faire.
 *
 * Ils n'ont aucun écran : la C2a est le moteur, la C2b sera le parcours.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-pas-a-pas-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->env = new EnvFile($this->files, $this->repertoire.'/.env');
    $this->files->put($this->repertoire.'/.env', "APP_NAME=Baobab\n");

    config()->set('baobab.install.state_path', $this->repertoire);
    app()->forgetInstance(InstallationState::class);
    $this->state = app(InstallationState::class);
    $this->pipeline = app(InstallationPipeline::class);
    $this->connexionInitiale = config('database.default');
});

/**
 * Même précaution que `InstallationPipelineTest` : `ConfigureDatabase` bascule
 * la connexion par défaut, et il faut la rendre **avant** d'effacer les
 * fichiers, sinon c'est le test suivant qui tombe (suivi n° 217, défaut trouvé
 * par la CI Linux et masqué par Windows).
 */
afterEach(function () {
    config()->set('database.default', $this->connexionInitiale);
    DB::purge('sqlite');

    $this->files->deleteDirectory($this->repertoire);
});

function entreePasAPas(): InstallationInput
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

function cheminsPasAPas(): array
{
    return ['storage' => storage_path()];
}

it('n\'avance que d\'une étape par appel, dans l\'ordre de la séquence', function () {
    $vues = [];

    while (($etape = $this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas())) !== null) {
        $vues[] = $etape->step;
    }

    // Le même ordre que la console, et pour la même raison : le hachage précède
    // le compte, sans quoi le premier compte serait haché autrement que le
    // reste du site (n° 220).
    expect($vues)->toBe(['database', 'migrations', 'hashing', 'account', 'site', 'finalization'])
        ->and($this->state->isInstalled())->toBeTrue();
});

/**
 * L'assertion qui compte ici est le **nombre d'appels** : un `advance()` qui
 * enchaînerait tout ferait passer le test précédent aussi bien que celui-ci
 * s'il ne comptait que l'ordre.
 */
it('laisse la main après chaque étape', function () {
    $premiere = $this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas());

    expect($premiere?->step)->toBe('database')
        ->and($this->state->isInstalled())->toBeFalse()
        ->and($this->state->hasCompleted('migrations'))->toBeFalse();
});

it('reprend là où l\'avancement s\'est arrêté', function () {
    $this->state->recordStep(InstallationPipeline::STEP_DATABASE);
    $this->state->recordStep(InstallationPipeline::STEP_MIGRATIONS);

    $etape = $this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas());

    expect($etape?->step)->toBe('hashing');
});

it('ne rend plus rien une fois le site installé', function () {
    while ($this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas()) !== null) {
        // On déroule jusqu'au bout.
    }

    // Sans la garde sur le lock, la finalisation se rejouerait sans fin :
    // elle est la seule étape que l'avancement ne note pas, le lock en
    // tenant lieu (§3).
    expect($this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas()))->toBeNull();
});

it('dit ce qu\'il reste à faire, d\'après l\'avancement réel', function () {
    expect($this->pipeline->remainingSteps())->toBe(['database', 'migrations', 'hashing', 'account', 'site', 'finalization']);

    $this->state->recordStep(InstallationPipeline::STEP_DATABASE);

    expect($this->pipeline->remainingSteps())->toBe(['migrations', 'hashing', 'account', 'site', 'finalization']);
});

/**
 * Le §6.1 veut une progression qui rende l'état réel. L'étape des migrations
 * est la seule assez longue pour qu'on veuille savoir ce qu'elle a fait, et
 * `direction-visuelle.md` demande de vrais noms plutôt qu'une barre abstraite.
 */
it('rend les migrations réellement passées', function () {
    $this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas());
    $migrations = $this->pipeline->advance(entreePasAPas(), $this->env, public_path(), cheminsPasAPas());

    expect($migrations?->step)->toBe('migrations')
        ->and($migrations->details)->not->toBeEmpty()
        ->and($migrations->details[0])->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_/');
});

it('nomme les étapes de la même façon pour les deux surfaces', function () {
    expect(InstallationPipeline::labelFor('migrations'))->toBe('Migrations')
        ->and(InstallationPipeline::labelFor('inconnue'))->toBe('inconnue');
});

it('accumule ce que les écrans collectent, et sait ce qui manque', function () {
    $draft = app(InstallDraft::class);

    expect($draft->isComplete())->toBeFalse()
        ->and($draft->missingSections())->toBe(['database', 'account', 'site']);

    $draft->put(InstallDraft::SECTION_DATABASE, ['driver' => 'mysql', 'database' => 'baobab']);
    $draft->put(InstallDraft::SECTION_ACCOUNT, ['email' => 'admin@exemple.fr']);

    expect($draft->missingSections())->toBe(['site'])
        ->and($draft->isComplete())->toBeFalse();

    $draft->put(InstallDraft::SECTION_SITE, ['name' => 'Mon site', 'url' => 'https://monsite.fr']);

    expect($draft->isComplete())->toBeTrue();
});

it('assemble l\'entrée du pipeline à partir du brouillon', function () {
    $draft = app(InstallDraft::class);
    $draft->put(InstallDraft::SECTION_DATABASE, [
        'driver' => 'mysql',
        'database' => 'baobab',
        'host' => 'db.exemple.fr',
        'port' => '3307',
        'username' => 'baobab',
        'password' => 'secret-de-base',
        'prefix' => 'bb_',
    ]);
    $draft->put(InstallDraft::SECTION_ACCOUNT, [
        'email' => 'admin@exemple.fr',
        'name' => 'Admin',
        'password' => 'secret-admin',
    ]);
    $draft->put(InstallDraft::SECTION_SITE, [
        'name' => 'Mon site',
        'url' => 'https://monsite.fr',
        'timezone' => 'Europe/Paris',
        'registration_open' => true,
    ]);

    $input = $draft->toInput('1.2.3', 'local', optimize: false);

    expect($input->database->driver)->toBe('mysql')
        ->and($input->database->port)->toBe(3307)
        ->and($input->database->prefix)->toBe('bb_')
        ->and($input->adminEmail)->toBe('admin@exemple.fr')
        ->and($input->adminPassword)->toBe('secret-admin')
        ->and($input->siteName)->toBe('Mon site')
        ->and($input->timezone)->toBe('Europe/Paris')
        ->and($input->registrationOpen)->toBeTrue()
        ->and($input->version)->toBe('1.2.3')
        ->and($input->optimize)->toBeFalse();
});

/**
 * Ce n'est pas de l'hygiène : ce brouillon porte deux mots de passe, et les
 * laisser en session après l'installation les ferait vivre aussi longtemps que
 * le fichier de session, sans qu'aucun écran ne les relise.
 */
it('efface le brouillon, mots de passe compris', function () {
    $draft = app(InstallDraft::class);
    $draft->put(InstallDraft::SECTION_ACCOUNT, ['email' => 'admin@exemple.fr', 'password' => 'secret-admin']);

    $draft->forget();

    expect($draft->has(InstallDraft::SECTION_ACCOUNT))->toBeFalse()
        ->and($draft->section(InstallDraft::SECTION_ACCOUNT))->toBe([]);
});
