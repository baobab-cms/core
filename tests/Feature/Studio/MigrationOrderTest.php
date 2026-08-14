<?php

use Baobab\ContentTypes\Generator\Exceptions\CircularTableDependencyException;
use Baobab\ContentTypes\Generator\MigrationFilename;
use Baobab\ContentTypes\Generator\MigrationOrder;

/**
 * Identité et ordre des migrations générées (suivi n° 120).
 *
 * Les deux faces du défaut sont vérifiées ici parce qu'elles ont la même
 * cause : tant que le nom porte une horloge et un tirage aléatoire, il décide
 * mal de l'ordre d'exécution *et* de l'identité à travers une distribution.
 */
it('ordonne une table référencée avant celle qui la référence', function () {
    // Le cas exact rencontré en conditions réelles : `instance_closures` porte
    // une clé étrangère vers `instances`, et sa migration s'exécutait avant.
    $order = MigrationOrder::sort([
        'instance_closures' => ['instances'],
        'instances' => [],
    ]);

    expect(array_search('instances', $order, true))
        ->toBeLessThan(array_search('instance_closures', $order, true));
});

it('place un pivot après ses deux côtés', function () {
    $order = MigrationOrder::sort([
        'article_tag' => ['articles', 'tags'],
        'articles' => [],
        'tags' => [],
    ]);

    $pivot = array_search('article_tag', $order, true);

    expect($pivot)->toBeGreaterThan(array_search('articles', $order, true))
        ->and($pivot)->toBeGreaterThan(array_search('tags', $order, true));
});

it('ignore une entité qui se référence elle-même', function () {
    // La colonne naît dans le même CREATE TABLE que la clé qu'elle vise :
    // ce n'est pas un cycle, et refuser la génération serait faux.
    $order = MigrationOrder::sort(['instances' => ['instances']]);

    expect($order)->toBe(['instances']);
});

it('ignore une cible hors du module, dont la table existe déjà', function () {
    $order = MigrationOrder::sort(['comments' => ['ct_articles']]);

    expect($order)->toBe(['comments']);
});

it('refuse un vrai cycle en le nommant, plutôt que de laisser échouer le SQL', function () {
    MigrationOrder::sort([
        'authors' => ['books'],
        'books' => ['authors'],
    ]);
})->throws(CircularTableDependencyException::class, 'authors');

it('produit un nom déterministe, identique d\'une machine à l\'autre', function () {
    // Le cœur du défaut de distribution : deux calculs du même plan, sur des
    // machines différentes et à des heures différentes, doivent produire le
    // même nom de fichier — sans quoi la base cible ne le reconnaît pas et
    // rejoue un CREATE TABLE sur une table existante.
    $first = MigrationFilename::create('/srv/a/modules/blog-core', 'blog_posts', 3);
    $second = MigrationFilename::create('/other/machine/modules/blog-core', 'blog_posts', 3);

    expect($first)->toBe($second)
        ->and($first)->toEndWith('_create_blog_posts_table.php')
        ->and($first)->toStartWith('database/migrations/0001_01_01_000003_');
});

it('distingue deux modules qui possèdent une table du même nom', function () {
    // Laravel enregistre une migration sous son seul nom de fichier : sans
    // discriminant de module, la table `cars` du second module serait tenue
    // pour déjà migrée et ne serait jamais créée.
    $fleet = MigrationFilename::create('/srv/modules/garage-fleet', 'cars', 1);
    $showroom = MigrationFilename::create('/srv/modules/garage-showroom', 'cars', 1);

    expect($fleet)->not->toBe($showroom);
});

it('trie les noms générés dans l\'ordre des rangs', function () {
    $dir = '/srv/modules/blog-core';

    $names = [
        MigrationFilename::create($dir, 'article_tag', 3),
        MigrationFilename::create($dir, 'articles', 1),
        MigrationFilename::create($dir, 'tags', 2),
    ];

    $sorted = $names;
    sort($sorted);

    // Laravel exécute les migrations dans l'ordre alphabétique des noms de
    // fichiers : le rang doit donc survivre au tri, sinon il ne sert à rien.
    expect($sorted[0])->toEndWith('_create_articles_table.php')
        ->and($sorted[1])->toEndWith('_create_tags_table.php')
        ->and($sorted[2])->toEndWith('_create_article_tag_table.php');
});

it('conserve le nom d\'un fichier déjà présent sur disque', function () {
    // Une installation existante a enregistré l'ancien nom aléatoire dans sa
    // table `migrations` : le renommer ferait rejouer une migration déjà
    // exécutée (suivi n° 116).
    $dir = sys_get_temp_dir().'/baobab-migration-name-'.getmypid();
    $migrations = $dir.'/database/migrations';

    mkdir($migrations, 0o777, true);
    touch($migrations.'/2026_08_10_095736_1addad_create_blog_posts_table.php');

    $name = MigrationFilename::create($dir, 'blog_posts', 1);

    expect($name)->toBe('database/migrations/2026_08_10_095736_1addad_create_blog_posts_table.php');

    unlink($migrations.'/2026_08_10_095736_1addad_create_blog_posts_table.php');
    rmdir($migrations);
    rmdir($dir.'/database');
    rmdir($dir);
});
