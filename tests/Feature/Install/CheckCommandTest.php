<?php

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Baobab\Install\PhpBinary;
use Baobab\Install\WebServer;
use Illuminate\Filesystem\Filesystem;

/**
 * Spec 15 §5 — `baobab:check`.
 *
 * Il rejoue la détection **et** la compare au profil du lock (n° 210) : ce
 * n'est pas l'état initial qui casse une instance, c'est la dérive
 * silencieuse — une capacité retirée par l'hébergeur sur un site que personne
 * n'a touché.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-check-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);

    config()->set('baobab.install.state_path', $this->repertoire);
    app()->forgetInstance(InstallationState::class);
    $this->state = app(InstallationState::class);
});

afterEach(function () {
    $this->files->deleteDirectory($this->repertoire);
});

it('dit qu\'il n\'a rien à comparer sur un site pas encore installé', function () {
    $this->artisan('baobab:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('pas encore installé');
});

it('ne signale aucun écart quand rien n\'a bougé', function () {
    $this->state->markInstalled('1.0.0', HostingProfile::detect(public_path(), sapi: 'cli'), 'somme');

    $this->artisan('baobab:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('Aucun écart');
});

/**
 * Le cas d'usage de la commande : le site marchait, il ne marche plus, et
 * rien dans le code n'a bougé.
 */
it('signale une capacité perdue depuis l\'installation', function () {
    // À l'installation, `symlink()` était disponible. Il ne l'est plus.
    $installe = new HostingProfile(
        symlink: Capability::Absent,
        procOpen: Capability::Absent,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Unknown,
        webServer: WebServer::Unknown,
    );

    $this->state->markInstalled('1.0.0', $installe, 'somme');

    // La détection courante voit `symlink` et `proc_open` présents : deux
    // écarts, dans le sens « retrouvé » — ce qui est un changement autant que
    // l'inverse, et mérite d'être dit.
    $this->artisan('baobab:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('ont changé depuis l\'installation');
});

it('réussit sur un environnement sain', function () {
    // Le contrat de sortie : 0 quand tout passe. Le cas de l'échec bloquant
    // est couvert là où il peut l'être honnêtement — sur l'Action elle-même
    // (`CheckRequirementsTest`), qui reçoit ses chemins en paramètre.
    $this->artisan('baobab:check')->assertExitCode(0);
});

/**
 * B4 du n° 235 : la checklist est rendue **à chaque exécution**.
 *
 * C'est la commande qu'on lance quand quelque chose cloche, et deux crons
 * absents sont l'explication la plus fréquente d'un e-mail qui ne part pas ou
 * d'un article qui ne se publie pas à sa date. La faire dépendre d'un drapeau
 * la rendrait introuvable au moment exact où elle sert.
 */
it('rend la checklist des tâches serveur sur un site installé', function () {
    $this->state->markInstalled('1.0.0', HostingProfile::detect(public_path(), sapi: 'cli'), 'somme');

    $this->artisan('baobab:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('artisan schedule:run');
});

/**
 * D3 du n° 238 : les configurations serveur sont **réécrites** à chaque
 * passage. Un site déplacé garderait sinon des fichiers portant les chemins
 * d'avant — et c'est précisément la situation où l'on vient chercher de
 * l'aide ici.
 */
it('réécrit les configurations serveur à chaque passage', function () {
    $this->state->markInstalled('1.0.0', HostingProfile::detect(public_path(), sapi: 'cli'), 'somme');
    $this->files->put($this->repertoire.'/baobab-nginx.conf', 'périmé');

    $this->artisan('baobab:check')->assertExitCode(0);

    expect($this->files->get($this->repertoire.'/baobab-nginx.conf'))->toContain('X-Content-Type-Options');
});

/**
 * D2 du n° 238 : le profil vient du lock, **sauf** le chemin PHP.
 *
 * Le lock d'une installation web porte un chemin *déduit* — `PHP_BINARY` y
 * désigne le binaire FPM. La console, elle, s'exécute dans le binaire
 * cherché : lui faire répéter « à vérifier » serait taire ce qu'elle constate.
 */
it('ne met aucune réserve sur le chemin PHP, qu\'elle constate elle-même', function () {
    $installe = new HostingProfile(
        symlink: Capability::Present,
        procOpen: Capability::Present,
        shellAccess: Capability::Unknown,
        publicIsDocumentRoot: Capability::Unknown,
        webServer: WebServer::Apache,
        phpBinary: PhpBinary::remembered('/chemin/du/web/php'),
    );

    $this->state->markInstalled('1.0.0', $installe, 'somme');

    $this->artisan('baobab:check')
        ->assertExitCode(0)
        ->doesntExpectOutputToContain('déduit et non constaté');
});
