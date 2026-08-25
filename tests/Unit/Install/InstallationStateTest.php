<?php

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Baobab\Install\WebServer;
use Illuminate\Filesystem\Filesystem;

/**
 * Spec 15 §3 et §4 — la sentinelle et l'avancement.
 *
 * Deux fichiers dont on confond facilement les rôles : `installed.lock` dit
 * qu'un site est installé et fait disparaître les routes de l'installateur ;
 * `install-state.json` ne sert qu'à reprendre une installation interrompue,
 * ce qu'un mutualisé rend probable.
 */
beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/baobab-install-'.bin2hex(random_bytes(6));
    $this->files = new Filesystem;
    $this->state = new InstallationState($this->files, $this->directory);
});

afterEach(function () {
    $this->files->deleteDirectory($this->directory);
});

function profileForTest(): HostingProfile
{
    return new HostingProfile(
        symlink: Capability::Present,
        procOpen: Capability::Absent,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Unknown,
        webServer: WebServer::Apache,
    );
}

it('ne se croit pas installé tant que la sentinelle n\'existe pas', function () {
    expect($this->state->isInstalled())->toBeFalse()
        ->and($this->state->lock())->toBeNull()
        ->and($this->state->installedProfile())->toBeNull();
});

it('journalise le profil dans le lock, pour que baobab:check puisse le relire', function () {
    $this->state->markInstalled('1.0.0', profileForTest(), 'abc123');

    expect($this->state->isInstalled())->toBeTrue();

    $profile = $this->state->installedProfile();

    expect($profile)->not->toBeNull()
        ->and($profile->symlink)->toBe(Capability::Present)
        ->and($profile->procOpen)->toBe(Capability::Absent)
        ->and($profile->webServer)->toBe(WebServer::Apache);

    $lock = $this->state->lock();
    expect($lock['version'])->toBe('1.0.0')
        ->and($lock['config_checksum'])->toBe('abc123');
});

it('retient les étapes passées pour qu\'une reprise ne les rejoue pas', function () {
    $this->state->recordStep('database');
    $this->state->recordStep('migrations');
    $this->state->recordStep('database');

    expect($this->state->completedSteps())->toBe(['database', 'migrations'])
        ->and($this->state->hasCompleted('migrations'))->toBeTrue()
        ->and($this->state->hasCompleted('account'))->toBeFalse();
});

it('efface l\'avancement à la finalisation — deux sources de vérité sur le même fait n\'en font pas une', function () {
    $this->state->recordStep('database');
    expect($this->files->isFile($this->state->statePath()))->toBeTrue();

    $this->state->markInstalled('1.0.0', profileForTest(), 'abc123');

    expect($this->files->isFile($this->state->statePath()))->toBeFalse()
        ->and($this->state->completedSteps())->toBe([]);
});

it('ne supprime jamais le lock en oubliant l\'avancement', function () {
    $this->state->markInstalled('1.0.0', profileForTest(), 'abc123');
    $this->state->recordStep('database');

    $this->state->forgetProgress();

    expect($this->state->isInstalled())->toBeTrue()
        ->and($this->state->completedSteps())->toBe([]);
});

it('survit à un fichier d\'état corrompu plutôt que de faire tomber l\'installateur', function () {
    $this->files->ensureDirectoryExists($this->directory);
    $this->files->put($this->state->statePath(), '{ ceci n est pas du json');

    expect($this->state->completedSteps())->toBe([]);
});
