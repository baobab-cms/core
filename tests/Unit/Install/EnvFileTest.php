<?php

use Baobab\Install\EnvFile;
use Illuminate\Filesystem\Filesystem;

/**
 * Spec 15 §4, étapes 2 et 5 — l'écriture de `.env`.
 *
 * Rien dans le Core n'écrivait ce fichier avant l'installateur. La règle que
 * ces tests protègent : **on modifie, on ne régénère pas**. Le `.env.example`
 * de l'archive porte des commentaires écrits pour quelqu'un qui n'a ni shell
 * ni documentation ; les perdre serait perdre ce qu'on a mis là pour lui.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->path = sys_get_temp_dir().'/baobab-env-'.bin2hex(random_bytes(6));
    $this->env = new EnvFile($this->files, $this->path);
});

afterEach(function () {
    $this->files->delete($this->path);
});

it('remplace une clé sur place, en gardant les commentaires autour', function () {
    $this->files->put($this->path, <<<'ENV'
# Les traductions vivent dans lang/ (suivi n° 204).
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

# SQLite est refusé en production (spec 15 §8, décision 3).
DB_CONNECTION=sqlite
ENV);

    $this->env->set(['DB_CONNECTION' => 'mysql']);

    $contenu = $this->files->get($this->path);

    expect($contenu)->toContain('# Les traductions vivent dans lang/')
        ->and($contenu)->toContain('# SQLite est refusé en production')
        ->and($contenu)->toContain('DB_CONNECTION=mysql')
        ->and($contenu)->not->toContain('DB_CONNECTION=sqlite')
        // L'ordre ne bouge pas : la clé est remplacée là où elle était.
        ->and(strpos($contenu, 'APP_LOCALE'))->toBeLessThan(strpos($contenu, 'DB_CONNECTION'));
});

it('ajoute à la fin une clé que le fichier ne connaissait pas', function () {
    $this->files->put($this->path, "APP_NAME=Baobab\n");

    $this->env->set(['DB_PREFIX' => 'monsite_']);

    expect($this->env->get('APP_NAME'))->toBe('Baobab')
        ->and($this->env->get('DB_PREFIX'))->toBe('monsite_');
});

/**
 * Un mot de passe de base contient volontiers une espace ou un dièse. Non
 * cité, il serait tronqué au premier de ces caractères — et la connexion
 * échouerait avec un message que personne ne relie à la cause.
 */
it('cite ce qui doit l\'être, et le relit à l\'identique', function (string $valeur) {
    $this->files->put($this->path, "DB_PASSWORD=\n");

    $this->env->set(['DB_PASSWORD' => $valeur]);

    expect($this->env->get('DB_PASSWORD'))->toBe($valeur);
})->with([
    'avec une espace' => 'mot de passe',
    'avec un diese' => 'a#b',
    'avec une apostrophe' => "l'ete",
    'avec un guillemet' => 'dit "bonjour"',
    'simple' => 'motdepasse123',
]);

it('ne prend pas une ligne commentée pour une clé', function () {
    $this->files->put($this->path, "# DB_CONNECTION=mysql\nDB_CONNECTION=sqlite\n");

    $this->env->set(['DB_CONNECTION' => 'pgsql']);

    $contenu = $this->files->get($this->path);

    expect($contenu)->toContain('# DB_CONNECTION=mysql')
        ->and($contenu)->toContain("\nDB_CONNECTION=pgsql");
});

it('écrit une valeur vide plutôt que de laisser traîner l\'ancienne', function () {
    $this->files->put($this->path, "DB_HOST=127.0.0.1\n");

    $this->env->set(['DB_HOST' => null]);

    expect($this->env->get('DB_HOST'))->toBe('');
});

it('crée le fichier depuis l\'exemple, et ne l\'écrase jamais ensuite', function () {
    $exemple = $this->path.'.example';
    $this->files->put($exemple, "APP_NAME=Exemple\n");

    $this->env->createFromExample($exemple);
    expect($this->env->get('APP_NAME'))->toBe('Exemple');

    // Une installation reprise après coupure ne doit pas perdre ce que
    // l'étape précédente avait écrit.
    $this->env->set(['APP_NAME' => 'Mon site']);
    $this->env->createFromExample($exemple);

    expect($this->env->get('APP_NAME'))->toBe('Mon site');

    $this->files->delete($exemple);
});

/**
 * Le `.env.example` de l'archive propose des clés **commentées**, entourées du
 * commentaire qui les explique. Les ajouter en fin de fichier laisserait la
 * version commentée sous les yeux de l'utilisateur et la vraie valeur soixante
 * lignes plus bas : le fichier paraîtrait inchangé là où il compte.
 *
 * Relevé en recette — l'utilisateur a lu le bloc « base de données », l'a vu
 * intact, et en a conclu que rien n'avait été écrit. Il avait raison de le
 * conclure.
 */
it('décommente une clé sur place plutôt que de l\'ajouter à la fin', function () {
    $this->files->put($this->path, <<<'ENV'
# SQLite convient à l'évaluation ; il est refusé en production.
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_DATABASE=baobab
# DB_USERNAME=root

MAIL_MAILER=log
ENV);

    $this->env->set(['DB_DATABASE' => 'monsite', 'DB_USERNAME' => 'compte']);

    $contenu = $this->files->get($this->path);
    $lignes = explode("\n", $contenu);

    expect($this->env->get('DB_DATABASE'))->toBe('monsite')
        ->and($this->env->get('DB_USERNAME'))->toBe('compte')
        // Plus aucune version commentée de ces clés ne subsiste.
        ->and($contenu)->not->toContain('# DB_DATABASE')
        ->and($contenu)->not->toContain('# DB_USERNAME')
        // ...et elles sont restées dans leur bloc, avant `MAIL_MAILER`.
        ->and(array_search('DB_DATABASE=monsite', $lignes, true))
        ->toBeLessThan(array_search('MAIL_MAILER=log', $lignes, true))
        // Le commentaire explicatif, lui, ne bouge pas.
        ->and($contenu)->toContain('# SQLite convient')
        // Et la clé commentée qu'on ne demandait pas reste commentée.
        ->and($contenu)->toContain('# DB_HOST=127.0.0.1');
});

it('ne prend pas une phrase de commentaire pour une clé', function () {
    $this->files->put($this->path, "# Utilisation = réservée aux tests\nAPP_NAME=Baobab\n");

    $this->env->set(['Utilisation' => 'production']);

    expect($this->files->get($this->path))->toContain('# Utilisation = réservée aux tests')
        ->and($this->env->get('Utilisation'))->toBe('production');
});
