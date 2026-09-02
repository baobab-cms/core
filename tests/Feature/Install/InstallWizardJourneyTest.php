<?php

use Baobab\Install\InstallationState;
use Baobab\Install\InstallDraft;
use Baobab\Install\InstallPaths;
use Baobab\Install\InstallToken;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 15 §6.1 — le parcours d'installation (Pass C2b, suivi n° 224).
 *
 * Ce que ces tests éprouvent, c'est **l'ordre du parcours** et le fait que
 * rien ne s'écrit avant qu'on ne le demande : le §6.1 exige « le retour arrière
 * possible avant finalisation », ce qui n'a de sens que si les écrans de
 * saisie ne déclenchent aucune étape.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-parcours-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);
    $this->files->put($this->repertoire.'/.env', "APP_NAME=Baobab\n");

    config()->set('baobab.install.state_path', $this->repertoire);
    config()->set('baobab.install.env_path', $this->repertoire.'/.env');
    config()->set('app.env', 'local');
    // Comme `InstallCommandTest` : un `config:cache` réellement exécuté ici
    // laisserait un fichier qui empoisonne toutes les exécutions suivantes,
    // y compris celles d'autres suites (suivi n° 217).
    config()->set('baobab.install.optimize', false);

    app()->forgetInstance(InstallationState::class);
    app()->forgetInstance(InstallToken::class);

    $this->state = app(InstallationState::class);
    $this->connexionInitiale = config('database.default');
});

afterEach(function () {
    config()->set('database.default', $this->connexionInitiale);
    DB::purge('sqlite');

    $this->files->deleteDirectory($this->repertoire);
});

/**
 * Le parcours vit derrière le jeton de la C1 : sans session ouverte, tout
 * renvoie à la porte. Chaque test commence donc par la franchir.
 */
function ouvrirSessionInstall(): void
{
    test()->get('/install');

    $chemin = test()->repertoire.'/install-token.txt';
    test()->post('/install', ['token' => trim(test()->files->get($chemin))]);
}

function saisirBase(array $remplacements = []): void
{
    test()->post('/install/database', array_merge([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], $remplacements));
}

function saisirCompte(array $remplacements = []): void
{
    test()->post('/install/account', array_merge([
        'email' => 'admin@exemple.fr',
        'password' => 'une phrase de passe',
        'password_confirmation' => 'une phrase de passe',
    ], $remplacements));
}

function saisirSite(array $remplacements = []): void
{
    test()->post('/install/site', array_merge([
        'name' => 'Mon site',
        'url' => 'https://monsite.fr',
        'timezone' => 'Europe/Paris',
    ], $remplacements));
}

it('montre les prérequis avant toute saisie', function () {
    ouvrirSessionInstall();

    $this->get('/install/start')
        ->assertOk()
        ->assertSee('Prérequis')
        ->assertSee('Commencer');
});

it('renvoie à la porte tant que le jeton n\'a pas été présenté', function () {
    $this->get('/install/database')->assertRedirect(route('baobab.install.gate'));
    $this->get('/install/run')->assertRedirect(route('baobab.install.gate'));
});

/**
 * La racine d'une archive fraîchement décompressée mène à l'installateur
 * (n° 213, demandé en recette le 2 septembre 2026). Quelqu'un qui décompresse
 * ouvre son domaine, pas `/install` : sans ce détour il tombe sur le rendu
 * public d'un site sans base, et rien ne lui dit quoi faire.
 *
 * La base hors d'atteinte est simulée en retirant la table que le détour
 * interroge : c'est l'état réel d'une archive, où aucune base n'existe encore.
 */
it('mène la racine à l\'installateur quand le site ne peut rien servir', function () {
    Schema::drop('users');

    $this->get('/')->assertRedirect(route('baobab.install.gate'))->assertStatus(302);
});

/**
 * **Le lock ne suffit pas à détourner.** Un site monté à la main — `composer
 * require` dans une application existante, ou ce banc d'essai — n'en a jamais
 * eu et fonctionne parfaitement. La première version de ce détour ne regardait
 * que le lock : elle a détourné seize tests de rendu public, ce qui est en
 * petit ce qu'elle aurait fait à ces sites-là.
 */
it('laisse sa page d\'accueil à un site déjà monté, lock ou pas', function () {
    expect($this->state->isInstalled())->toBeFalse();

    $this->get('/')->assertOk();
});

it('collecte la base sans rien écrire', function () {
    ouvrirSessionInstall();
    saisirBase(['prefix' => 'bb_']);

    $draft = app(InstallDraft::class);

    expect($draft->section(InstallDraft::SECTION_DATABASE)['prefix'])->toBe('bb_')
        // Rien ne doit avoir été fait : c'est ce qui rend le retour arrière réel.
        ->and($this->state->completedSteps())->toBe([])
        ->and($this->state->isInstalled())->toBeFalse();
});

it('interdit de sauter un écran de saisie', function () {
    ouvrirSessionInstall();

    $this->get('/install/account')->assertRedirect(route('baobab.install.database'));

    saisirBase();

    $this->get('/install/site')->assertRedirect(route('baobab.install.account'));
});

it('exige un mot de passe long et confirmé', function () {
    ouvrirSessionInstall();
    saisirBase();

    $this->post('/install/account', [
        'email' => 'admin@exemple.fr',
        'password' => 'trop court',
        'password_confirmation' => 'trop court',
    ])->assertSessionHasErrors('password');

    $this->post('/install/account', [
        'email' => 'admin@exemple.fr',
        'password' => 'une phrase de passe',
        'password_confirmation' => 'une autre phrase',
    ])->assertSessionHasErrors('password');
});

/**
 * §8 point 2 : opt-in **explicite**, décochée par défaut. Une case cochée
 * d'avance n'est pas un consentement, et c'est le genre de détail qu'une
 * refonte d'écran remet à l'envers sans y penser.
 */
it('laisse la télémétrie refusée quand la case n\'est pas cochée', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite();

    expect(app(InstallDraft::class)->section(InstallDraft::SECTION_SITE)['telemetry'])->toBeFalse();
});

it('retient la télémétrie quand la case est cochée', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite(['telemetry' => '1']);

    expect(app(InstallDraft::class)->section(InstallDraft::SECTION_SITE)['telemetry'])->toBeTrue();
});

/**
 * §8 point 3 : SQLite est interdit en production, et l'option y est **absente**
 * plutôt que grisée — une option grisée invite à chercher comment la forcer.
 */
it('ne propose pas SQLite en production', function () {
    config()->set('app.env', 'production');
    ouvrirSessionInstall();

    $this->get('/install/database')
        ->assertOk()
        ->assertSee('MySQL 8 ou plus')
        ->assertDontSee('SQLite');
});

it('ramène à l\'écran manquant si l\'on demande la progression trop tôt', function () {
    ouvrirSessionInstall();
    saisirBase();

    $this->get('/install/run')->assertRedirect(route('baobab.install.account'));
});

it('n\'avance que d\'une étape par requête', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite();

    $this->postJson('/install/step')
        ->assertOk()
        ->assertJson(['done' => false, 'step' => 'database']);

    expect($this->state->hasCompleted('database'))->toBeTrue()
        ->and($this->state->hasCompleted('migrations'))->toBeFalse();
});

it('rend les migrations passées à l\'étape qui les a passées', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite();

    $this->postJson('/install/step');
    $reponse = $this->postJson('/install/step')->assertOk();

    expect($reponse->json('step'))->toBe('migrations')
        ->and($reponse->json('details'))->not->toBeEmpty();
});

/**
 * Le parcours complet — et la vérification qui compte le plus : les deux mots
 * de passe ne survivent pas à l'installation.
 */
it('mène l\'installation de bout en bout, puis oublie les secrets', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite();

    $fin = null;

    for ($appels = 0; $appels < 10; $appels++) {
        $reponse = $this->postJson('/install/step')->assertOk();

        if ($reponse->json('done') === true) {
            $fin = $reponse;
            break;
        }
    }

    expect($fin)->not->toBeNull()
        ->and($fin->json('adminUrl'))->toBe('https://monsite.fr/admin')
        ->and($this->state->isInstalled())->toBeTrue()
        ->and(app(InstallDraft::class)->has(InstallDraft::SECTION_ACCOUNT))->toBeFalse();
});

/**
 * Sans JavaScript, chaque envoi du formulaire de repli exécute une étape et
 * revient à l'écran de progression. C'est laid, et c'est mieux qu'une page qui
 * ne fait rien — le public de l'archive n'a pas toujours un navigateur récent.
 */
it('avance aussi sans JavaScript', function () {
    ouvrirSessionInstall();
    saisirBase();
    saisirCompte();
    saisirSite();

    $this->post('/install/step')->assertRedirect(route('baobab.install.run'));

    expect($this->state->hasCompleted('database'))->toBeTrue();
});

/**
 * Défaut trouvé en recette navigateur le 28 août 2026 — et il aurait bloqué
 * **toute** installation par archive.
 *
 * `.env.example` livre `SESSION_DRIVER=database`, le défaut de Laravel. Or à
 * l'installation il n'y a **aucune base** : c'est l'utilisateur qui la
 * désigne. Le pilote ne peut donc pas fonctionner, et la session de
 * l'installateur — celle qui porte le verrou exclusif et le brouillon, donc
 * les identifiants déjà saisis — se serait perdue au premier écran.
 *
 * Aucun test ne pouvait le voir : la suite tourne sur un pilote de session qui
 * ne touche pas la base. Il a fallu un navigateur, et un `.env` de banc d'essai
 * configuré comme l'archive le sera.
 */
it('débranche de la base tout ce qui n\x27en a pas besoin à l\x27installation', function () {
    $config = new Repository([
        'session' => ['driver' => 'database', 'files' => $this->repertoire.'/sessions'],
        'cache' => ['default' => 'database'],
        'queue' => ['default' => 'database'],
    ]);

    InstallPaths::useLocalDrivers($config, $this->files);

    // Le cache est le plus critique des trois : le throttling du §6.2
    // passe par le RateLimiter, qui passe par le cache — sans ce
    // débranchement, la porte elle-même tombe.
    expect($config->get('session.driver'))->toBe('file')
        ->and($config->get('cache.default'))->toBe('file')
        ->and($config->get('queue.default'))->toBe('sync')
        ->and($this->files->isDirectory($this->repertoire.'/sessions'))->toBeTrue();
});

it('ne touche pas à une configuration qui n\x27utilise déjà pas la base', function () {
    $config = new Repository([
        'session' => ['driver' => 'file', 'files' => $this->repertoire.'/intacte'],
        'cache' => ['default' => 'redis'],
        'queue' => ['default' => 'redis'],
    ]);

    InstallPaths::useLocalDrivers($config, $this->files);

    expect($config->get('cache.default'))->toBe('redis')
        ->and($config->get('queue.default'))->toBe('redis')
        ->and($this->files->isDirectory($this->repertoire.'/intacte'))->toBeFalse();
});

/**
 * Défaut trouvé le 28 août 2026 sur une archive réellement décompressée, et il
 * empêchait l'installateur de **s'afficher**.
 *
 * Le zip ne contient pas de `.env` — c'est voulu — et son `.env.example` livre
 * `APP_KEY=` vide. Or `/install` passe par le groupe `web`, donc par
 * `EncryptCookies`, qui exige une clé : `MissingAppKeyException` était levée
 * avant tout contrôleur. La spec prévoit bien la génération, mais à l'étape 2 —
 * une fois le formulaire de base rempli, c'est-à-dire jamais.
 */
it('se donne une clé applicative avant d\'ouvrir la moindre page', function () {
    $config = new Repository(['app' => ['key' => '']]);
    config()->set('baobab.install.env_path', $this->repertoire.'/neuf.env');

    InstallPaths::ensureApplicationKey($config, $this->files);

    // La clé peut venir du `.env.example` — testbench en fournit un — ou
    // être forgée. Ce qui compte est qu'il y en ait une, et que le fichier
    // existe : sans elle, `EncryptCookies` refuse la requête.
    expect($config->get('app.key'))->not->toBe('')
        ->and($this->files->isFile($this->repertoire.'/neuf.env'))->toBeTrue();
});

/**
 * La clé chiffre les sessions : en générer une nouvelle à chaque requête
 * rendrait illisible, d'une page à l'autre, la session qui porte le verrou et
 * le brouillon. Ce test verrouille la stabilité, pas la génération.
 */
it('ne régénère pas la clé une fois qu\'elle existe', function () {
    config()->set('baobab.install.env_path', $this->repertoire.'/stable.env');

    $premier = new Repository(['app' => ['key' => '']]);
    InstallPaths::ensureApplicationKey($premier, $this->files);

    $second = new Repository(['app' => ['key' => '']]);
    InstallPaths::ensureApplicationKey($second, $this->files);

    expect($second->get('app.key'))->toBe($premier->get('app.key'));
});

/**
 * Le cas de l'archive : un `.env.example` livrant `APP_KEY=` vide. Il faut
 * alors en forger une, sans quoi rien ne s'ouvre.
 */
it('forge une clé quand l\x27exemple n\x27en fournit aucune', function () {
    $this->files->put($this->repertoire.'/vide.env', 'APP_NAME=Baobab
APP_KEY=
');
    config()->set('baobab.install.env_path', $this->repertoire.'/vide.env');

    $config = new Repository(['app' => ['key' => '']]);
    InstallPaths::ensureApplicationKey($config, $this->files);

    expect($config->get('app.key'))->toStartWith('base64:')
        ->and($this->files->get($this->repertoire.'/vide.env'))->toContain('APP_KEY=base64:');
});

/**
 * Défaut signalé en recette le 28 août 2026 : le préfixe saisi à l'écran
 * « Base » ne se retrouvait pas sur les tables créées. Le chemin console est
 * couvert par `PrefixedInstallTest` ; celui du wizard ne l'était pas.
 */
it('applique le préfixe saisi aux tables qu\'il crée', function () {
    ouvrirSessionInstall();
    saisirBase(['prefix' => 'bb_']);
    saisirCompte();
    saisirSite();

    for ($appels = 0; $appels < 10; $appels++) {
        if ($this->postJson('/install/step')->json('done') === true) {
            break;
        }
    }

    // La configuration ne suffit pas : ce qui compte est le nom réel des
    // tables sur le disque. C'est précisément l'écart que la recette a vu.
    $tables = DB::connection(config('database.default'))
        ->select("select name from sqlite_master where type = 'table'");
    $noms = array_map(fn ($t): string => $t->name, $tables);

    expect(config('database.connections.'.config('database.default').'.prefix'))->toBe('bb_')
        ->and($noms)->toContain('bb_users')
        ->and($noms)->not->toContain('users');
});
