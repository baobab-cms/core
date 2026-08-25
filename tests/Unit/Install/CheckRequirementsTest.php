<?php

use Baobab\Install\Actions\CheckRequirements;
use Illuminate\Filesystem\Filesystem;

/**
 * Spec 15 §4, étape 1 — ce qui bloque, ce qui avertit, et le profil.
 *
 * L'Action ne modifie rien : c'est la condition pour que `baobab:check` la
 * rejoue sur une instance en production (suivi n° 210).
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->racine = sys_get_temp_dir().'/baobab-req-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->racine);
    $this->action = new CheckRequirements($this->files);
});

afterEach(function () {
    $this->files->deleteDirectory($this->racine);
});

it('passe sur l\'environnement qui fait tourner cette suite', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine]);

    expect($report->passes())->toBeTrue()
        ->and($report->blockingFailures())->toBe([]);
});

it('bloque sur un répertoire absent, et dit quoi faire', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine.'/nexiste-pas']);

    $echec = $report->get('writable.storage');

    expect($report->passes())->toBeFalse()
        ->and($echec->satisfied)->toBeFalse()
        ->and($echec->blocking)->toBeTrue()
        // La spec impose un remède, pas seulement un « non » : le public visé
        // n'a ni shell ni root pour deviner la suite.
        ->and($echec->remedy)->toContain('FTP');
});

it('n\'arrête jamais l\'installation sur la mémoire — c\'est un avertissement', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine]);

    $memoire = $report->get('memory');

    expect($memoire->blocking)->toBeFalse();

    // Quel que soit le verdict, il ne peut pas figurer parmi les blocages.
    $cles = array_map(static fn ($r) => $r->key, $report->blockingFailures());
    expect($cles)->not->toContain('memory');
});

it('accepte gd ou imagick, sans exiger les deux', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine]);

    expect($report->get('ext.image')->satisfied)
        ->toBe(extension_loaded('gd') || extension_loaded('imagick'));
});

it('rend le profil du même geste que les exigences', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine], [
        'SERVER_SOFTWARE' => 'nginx/1.24.0',
        'DOCUMENT_ROOT' => $this->racine.'/public',
    ]);

    expect($report->profile->webServer->value)->toBe('nginx');
});

it('exige le plancher PHP de la spec, et le dit avec la version constatée', function () {
    $report = ($this->action)($this->racine.'/public', ['storage' => $this->racine]);

    $php = $report->get('php');

    expect($php->satisfied)->toBeTrue()
        ->and($php->detail)->toContain(PHP_VERSION)
        ->and(CheckRequirements::MINIMUM_PHP)->toBe('8.4.0');
});
