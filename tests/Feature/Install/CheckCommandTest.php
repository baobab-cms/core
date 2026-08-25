<?php

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
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
