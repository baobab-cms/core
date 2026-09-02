<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Facades\Hook;
use Baobab\Install\Actions\ConfigureSite;
use Baobab\Install\Actions\FinalizeInstallation;
use Baobab\Install\Capability;
use Baobab\Install\EnvFile;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallToken;
use Baobab\Install\WebServer;
use Baobab\Telemetry\Models\TelemetrySetting;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Spec 15 §6.3 — l'autodestruction et ce que l'installation déclare en
 * partant. Pass C3a (suivi n° 229).
 *
 * Ce fichier existe parce que le §6.3 était **écrit et inopérant** : le geste
 * de destruction visait `public/install/`, un répertoire que l'amendement du
 * 26 août 2026 avait déplacé, et aucun test ne regardait le répertoire. Les
 * tests qui suivent portent donc sur des chemins **réels**, jamais sur
 * l'intention du code.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-finalisation-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);

    // Un seul état et un seul jeton pour le conteneur : sans cela, l'Action
    // écrirait son lock et supprimerait son jeton ailleurs que là où le test
    // regarde, et il passerait sur des fichiers que personne ne lit.
    config()->set('baobab.install.state_path', $this->repertoire);
    app()->forgetInstance(InstallationState::class);
    app()->forgetInstance(InstallToken::class);

    $this->assets = public_path('baobab/install');
    $this->files->ensureDirectoryExists($this->assets);
    $this->files->put($this->assets.'/wizard.css', ':root{}');
});

afterEach(function () {
    $this->files->deleteDirectory($this->assets);
    $this->files->deleteDirectory($this->repertoire);
});

function profilDeTest(): HostingProfile
{
    return new HostingProfile(
        symlink: Capability::Absent,
        procOpen: Capability::Present,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Unknown,
        webServer: WebServer::Unknown,
        argon2id: Capability::Present,
    );
}

function finaliser(): HostingProfile
{
    return app(FinalizeInstallation::class)('1.0.0', profilDeTest(), 'empreinte', optimize: false);
}

it('supprime les assets du wizard là où ils vivent réellement', function () {
    expect($this->files->isFile($this->assets.'/wizard.css'))->toBeTrue();

    finaliser();

    expect($this->files->isDirectory($this->assets))->toBeFalse();
});

it('supprime le jeton en le demandant à son porteur, pas à un chemin recopié', function () {
    $jeton = app(InstallToken::class);
    $jeton->value();

    expect($this->files->isFile($jeton->path()))->toBeTrue()
        ->and($jeton->path())->toBe($this->repertoire.'/install-token.txt');

    finaliser();

    expect($this->files->isFile($jeton->path()))->toBeFalse();
});

it('écrit l\'entrée d\'audit initiale, sans acteur', function () {
    finaliser();

    $entree = AuditEntry::query()->where('action', 'install.completed')->sole();

    expect($entree->actor_id)->toBeNull()
        ->and($entree->auditable_type)->toBeNull()
        ->and($entree->data)->toHaveKeys(['version', 'php', 'database', 'mode', 'telemetry', 'profile'])
        ->and($entree->data['version'])->toBe('1.0.0');
});

/**
 * §8 point 2 : la liste des données transmissibles est fermée et publique.
 * Le test la garde fermée — il échouera le jour où quelqu'un ajoutera l'URL
 * du site ou le nom de l'administrateur « juste pour diagnostiquer ».
 */
it('ne met dans la charge ni identifiant, ni URL, ni contenu', function () {
    finaliser();

    $donnees = AuditEntry::query()->where('action', 'install.completed')->sole()->data;

    expect(array_keys($donnees))->toBe(['version', 'php', 'database', 'mode', 'telemetry', 'profile']);
});

it('émet baobab.installed avec la charge de l\'audit', function () {
    $recue = null;

    Hook::listen('baobab.installed', function (array $payload) use (&$recue): void {
        $recue = $payload;
    });

    finaliser();

    expect($recue)->not->toBeNull()
        ->and($recue['version'])->toBe('1.0.0')
        ->and($recue['mode'])->toBe('cli')
        ->and($recue['profile']['proc_open'])->toBe('present');
});

/**
 * Le lock est ce qui rend l'installation vraie. Une trace qui échoue ne doit
 * ni la défaire, ni faire remonter une erreur à quelqu'un dont le site vient
 * d'être installé — c'est le défaut n° 226 vu depuis l'autre bout.
 */
it('n\'annonce pas l\'échec d\'une trace à quelqu\'un dont le site est installé', function () {
    Schema::drop('audit_log');

    finaliser();

    expect(app(InstallationState::class)->isInstalled())->toBeTrue();
});

it('persiste le consentement à la télémétrie quand il est donné', function () {
    $env = new EnvFile($this->files, $this->repertoire.'/.env');

    app(ConfigureSite::class)($env, 'Mon site', 'https://monsite.fr', 'UTC', true, true);

    expect(TelemetrySetting::isEnabled())->toBeTrue();
});

/**
 * Le défaut que ce test aurait attrapé, et qui a coûté deux recettes.
 *
 * `config/session.php` dérive le nom du cookie de `APP_NAME`. Écrire le nom du
 * site déplaçait donc le cookie : la requête suivante en cherchait un que le
 * navigateur n'avait pas, la session d'installation — verrou et brouillon
 * compris — disparaissait, et le wizard recevait la page de la porte là où il
 * attendait du JSON (suivi n° 226).
 */
it('fige le nom du cookie de session, pour qu\'il cesse de suivre le nom du site', function () {
    $env = new EnvFile($this->files, $this->repertoire.'/.env');
    $enVigueur = (string) config('session.cookie');

    app(ConfigureSite::class)($env, 'Baobab LMS', 'https://monsite.fr');

    expect($env->get('SESSION_COOKIE'))->toBe($enVigueur);
});

/**
 * **La valeur figée est celle en vigueur, jamais une dérivée du nouveau nom.**
 * Écrire `baobab-lms-session` ferait exactement ce que le correctif empêche :
 * changer le cookie sous la session courante.
 */
it('ne dérive pas le cookie figé du nom que l\'on vient de choisir', function () {
    $env = new EnvFile($this->files, $this->repertoire.'/.env');

    app(ConfigureSite::class)($env, 'Baobab LMS', 'https://monsite.fr');

    expect($env->get('SESSION_COOKIE'))->not->toBe(Str::slug('Baobab LMS').'-session');
});

it('laisse la télémétrie refusée quand rien n\'a été coché', function () {
    $env = new EnvFile($this->files, $this->repertoire.'/.env');

    app(ConfigureSite::class)($env, 'Mon site', 'https://monsite.fr');

    expect(TelemetrySetting::isEnabled())->toBeFalse();
});
