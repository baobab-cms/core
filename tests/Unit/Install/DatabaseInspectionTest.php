<?php

use Baobab\Install\DatabaseInspection;

/**
 * Spec 15 §4, étape 2 — une base occupée n'est pas une erreur.
 *
 * C'est le cas courant du mutualisé, qui n'en donne souvent qu'une :
 * l'installateur avertit et propose un préfixe, il ne refuse pas.
 */
it('voit une base vide pour ce qu\'elle est', function () {
    expect((new DatabaseInspection([]))->isEmpty())->toBeTrue()
        ->and((new DatabaseInspection(['wp_posts']))->isEmpty())->toBeFalse();
});

it('cesse de compter comme conflit ce qu\'un préfixe fait cohabiter', function () {
    $sansPrefixe = new DatabaseInspection(['wp_posts', 'wp_users']);
    $avecPrefixe = new DatabaseInspection(['wp_posts', 'wp_users'], 'monsite_');

    expect($sansPrefixe->conflicts())->toBe(['wp_posts', 'wp_users'])
        // Tout l'intérêt de la proposition : ces tables ne gênent plus.
        ->and($avecPrefixe->conflicts())->toBe([]);
});

/**
 * Tiré au sort, jamais dérivé du nom du site.
 *
 * Un nom de site change au moins une fois ; les noms de tables, jamais. Un
 * préfixe `mon_site_` survivrait au renommage et désignerait un site qui ne
 * s'appelle plus ainsi — il mentirait, ce qui est pire qu'un préfixe muet.
 */
it('tire trois lettres au sort, sans chiffre en tête', function () {
    $inspection = new DatabaseInspection([]);

    foreach (range(1, 25) as $ignore) {
        expect($inspection->suggestPrefix())->toMatch('/^[a-z]{3}_$/');
    }
});

it('ne rend pas deux fois le même préfixe, à vue de nez', function () {
    $inspection = new DatabaseInspection([]);

    $tirages = [];
    foreach (range(1, 20) as $ignore) {
        $tirages[] = $inspection->suggestPrefix();
    }

    // 17 576 combinaisons : vingt tirages identiques signaleraient un
    // générateur bloqué, pas un coup de chance.
    expect(count(array_unique($tirages)))->toBeGreaterThan(1);
});

/**
 * Le vrai test de la boucle : on occupe **toutes** les combinaisons commençant
 * par `a` — 676 tables — et on tire assez souvent pour que le générateur en
 * propose forcément plusieurs. Aucune ne doit sortir.
 *
 * Sans cela, le contrôle de collision serait affirmé et jamais exercé : avec
 * 17 576 combinaisons, un test naïf passerait même si la boucle était morte.
 */
it('retire les préfixes déjà pris jusqu\'à en trouver un libre', function () {
    $occupees = [];

    foreach (range('a', 'z') as $deuxieme) {
        foreach (range('a', 'z') as $troisieme) {
            $occupees[] = 'a'.$deuxieme.$troisieme.'_modules';
        }
    }

    $inspection = new DatabaseInspection($occupees);

    foreach (range(1, 300) as $ignore) {
        expect($inspection->suggestPrefix())->not->toStartWith('a');
    }
});

it('rend le préfixe déjà écrit, pour qu\'une reprise ne crée pas un second jeu de tables', function () {
    $inspection = new DatabaseInspection(['xkq_modules']);

    expect($inspection->suggestPrefix('xkq_'))->toBe('xkq_')
        ->and($inspection->suggestPrefix('  '))->toMatch('/^[a-z]{3}_$/');
})->note('Spec 15 §4 — une installation reprise après coupure doit retrouver le sien.');
