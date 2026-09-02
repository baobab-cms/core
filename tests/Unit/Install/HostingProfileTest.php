<?php

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\PhpBinary;
use Baobab\Install\WebServer;

/**
 * Spec 15 §4.1 — « détecter, jamais niveler ».
 *
 * Le point que ces tests protègent : **une capacité peut être indéterminée**.
 * Le serveur web et la disposition de la racine ne se lisent que dans une
 * requête HTTP ; répondre « absente » en console ferait dire à la checklist
 * ce qu'elle n'a pas constaté.
 */
it('ne conclut rien sur le serveur web depuis la console', function () {
    $profile = HostingProfile::detect('/srv/site/public', server: [], sapi: 'cli');

    expect($profile->webServer)->toBe(WebServer::Unknown)
        ->and($profile->publicIsDocumentRoot)->toBe(Capability::Unknown);
});

it('tient l\'accès shell pour acquis quand on s\'exécute déjà dans un shell', function () {
    expect(HostingProfile::detect('/srv/site/public', sapi: 'cli')->shellAccess)->toBe(Capability::Present)
        ->and(HostingProfile::detect('/srv/site/public', sapi: 'fpm-fcgi')->shellAccess)->toBe(Capability::Unknown);
});

it('reconnaît le serveur web depuis une requête', function (string $software, WebServer $attendu) {
    $profile = HostingProfile::detect('/srv/site/public', ['SERVER_SOFTWARE' => $software], 'fpm-fcgi');

    expect($profile->webServer)->toBe($attendu);
})->with([
    ['Apache/2.4.58 (Unix)', WebServer::Apache],
    ['nginx/1.24.0', WebServer::Nginx],
    ['LiteSpeed', WebServer::Other],
]);

it('constate si public/ est bien la racine de document, en tolérant les séparateurs Windows', function () {
    $memeChemin = HostingProfile::detect('C:/site/public', [
        'DOCUMENT_ROOT' => 'C:'.chr(92).'site'.chr(92).'public'.chr(92),
    ], 'fpm-fcgi');

    $autreChemin = HostingProfile::detect('/srv/site/public', [
        'DOCUMENT_ROOT' => '/srv/site',
    ], 'fpm-fcgi');

    expect($memeChemin->publicIsDocumentRoot)->toBe(Capability::Present)
        ->and($autreChemin->publicIsDocumentRoot)->toBe(Capability::Absent);
});

it('fait l\'aller-retour par le tableau du lock sans rien perdre', function () {
    $profile = new HostingProfile(
        symlink: Capability::Absent,
        procOpen: Capability::Present,
        shellAccess: Capability::Unknown,
        publicIsDocumentRoot: Capability::Present,
        webServer: WebServer::Nginx,
    );

    expect(HostingProfile::fromArray($profile->toArray()))->toEqual($profile);
});

it('relit un lock amputé sans se plaindre, en retombant sur l\'indéterminé', function () {
    $profile = HostingProfile::fromArray(['symlink' => 'present']);

    expect($profile->symlink)->toBe(Capability::Present)
        ->and($profile->procOpen)->toBe(Capability::Unknown)
        ->and($profile->webServer)->toBe(WebServer::Unknown);
});

/**
 * C'est la dérive, non l'état initial, qui casse une instance que personne
 * n'a touchée — un `symlink()` désactivé par l'hébergeur après coup, par
 * exemple. Mais passer de connu à indéterminé n'est pas une dérive : c'est
 * seulement qu'on observe depuis la console ce qui avait été vu depuis le web.
 */
it('signale une capacité perdue depuis l\'installation', function () {
    $installe = new HostingProfile(Capability::Present, Capability::Present, Capability::Present, Capability::Present, WebServer::Nginx);
    $aujourdhui = new HostingProfile(Capability::Absent, Capability::Present, Capability::Present, Capability::Present, WebServer::Nginx);

    expect($aujourdhui->driftFrom($installe))->toBe([
        'symlink' => ['avant' => 'present', 'apres' => 'absent'],
    ]);
});

it('ne prend pas une observation impossible pour une dérive', function () {
    $installe = new HostingProfile(Capability::Present, Capability::Present, Capability::Present, Capability::Present, WebServer::Nginx);
    $enConsole = new HostingProfile(Capability::Present, Capability::Present, Capability::Present, Capability::Unknown, WebServer::Unknown);

    expect($enConsole->driftFrom($installe))->toBe([]);
});

/**
 * Le chemin de PHP entre au profil (Pass C3b1, n° 235) : les deux entrées cron
 * du §7 en dépendent, et son déplacement — un hébergeur qui change de version
 * de PHP — casse une tâche planifiée sans que rien ne le dise.
 */
it('retient le chemin de PHP, constaté depuis la console', function () {
    $profil = HostingProfile::detect('/var/www/public', [], 'cli');

    expect($profil->phpBinary?->path)->toBe(PHP_BINARY)
        ->and($profil->phpBinary?->confirmed)->toBeTrue()
        ->and($profil->toArray()['php_binary'])->toBe(PHP_BINARY);
});

it('signale un déplacement du binaire PHP comme une dérive', function () {
    $installe = HostingProfile::fromArray([
        'symlink' => 'present',
        'proc_open' => 'present',
        'shell_access' => 'present',
        'public_is_document_root' => 'present',
        'web_server' => 'apache',
        'argon2id' => 'present',
        'php_binary' => '/usr/bin/php8.3',
    ]);

    $aujourdhui = new HostingProfile(
        symlink: Capability::Present,
        procOpen: Capability::Present,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Present,
        webServer: WebServer::Apache,
        argon2id: Capability::Present,
        phpBinary: PhpBinary::remembered('/usr/bin/php8.4'),
    );

    expect($aujourdhui->driftFrom($installe))->toBe([
        'php_binary' => ['avant' => '/usr/bin/php8.3', 'apres' => '/usr/bin/php8.4'],
    ]);
});

/**
 * Un lock écrit avant que cette clé n'existe ne doit pas faire lever la
 * comparaison — il doit dire « on ne sait pas », ce que `driftFrom()` ignore.
 */
it('compare sans se plaindre un profil neuf à un lock qui ignore ses clés', function () {
    $ancien = HostingProfile::fromArray(['symlink' => 'present']);

    expect(HostingProfile::detect('/var/www/public', [], 'cli')->driftFrom($ancien))->toBe([]);
});

/**
 * `withoutSymlink()` existe parce qu'une recopie champ par champ oublie ce
 * qu'on ajoute après elle : la finalisation perdait `argon2id` (n° 235).
 */
it('ne perd rien en constatant que le lien symbolique a échoué', function () {
    $profil = new HostingProfile(
        symlink: Capability::Present,
        procOpen: Capability::Present,
        shellAccess: Capability::Present,
        publicIsDocumentRoot: Capability::Present,
        webServer: WebServer::Apache,
        argon2id: Capability::Present,
        phpBinary: PhpBinary::remembered('/usr/bin/php'),
    );

    $sansLien = $profil->withoutSymlink();

    expect($sansLien->symlink)->toBe(Capability::Absent)
        ->and($sansLien->argon2id)->toBe(Capability::Present)
        ->and($sansLien->phpBinary?->path)->toBe('/usr/bin/php')
        ->and($sansLien->webServer)->toBe(WebServer::Apache);
});
