<?php

use Baobab\Install\ChecklistFile;
use Baobab\Install\ChecklistItem;
use Baobab\Install\Console\InstallerOutput;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * L'adaptateur console de la checklist (spec 15 §7, Pass C3b2).
 *
 * **Éprouvé ici et non à travers `baobab:install`** : la commande tourne en
 * `--no-interaction` dans la suite, mode où `InstallerOutput` est muet par
 * contrat — c'est la CI et le provisioning d'agence qu'il sert alors, où
 * l'ornement est du bruit dans un journal. Le seul endroit honnête pour
 * regarder ce rendu est donc l'objet qui le produit.
 */
function sortie(array $items): string
{
    $tampon = new BufferedOutput;
    $ui = new InstallerOutput(new OutputStyle(new ArrayInput([]), $tampon), quiet: false);

    $ui->checklist($items);

    return $tampon->fetch();
}

/**
 * **La ligne à coller n'est jamais reformatée.** Elle part dans un panneau
 * d'hébergement : un retour à la ligne inséré par nos soins y produirait une
 * tâche cron silencieusement fausse, le mode de panne exact que le §7 existe
 * pour prévenir.
 */
it('rend la ligne de cron intacte, si longue soit-elle', function () {
    $ligne = 'cd /home/user/monsite && /usr/local/bin/php artisan queue:work database '
        .'--queue=baobab,baobab-low,default --tries=3 --stop-when-empty >> storage/logs/cron-queue.log 2>&1';

    expect(sortie([ChecklistItem::task('cron.worker', 'Le worker', 'Un corps.', command: $ligne)]))
        ->toContain($ligne);
});

/**
 * Un item de checklist n'est pas un verdict : ni « OK », ni « ÉCHEC ». Peindre
 * ces lignes comme des prérequis ferait lire six tâches restantes comme six
 * échecs, sur l'écran qui annonce une réussite.
 */
it('ne rend aucun verdict sur des tâches à faire', function () {
    $rendu = sortie([ChecklistItem::task('cron.scheduler', 'Planifier', 'Un corps.')]);

    expect($rendu)->not->toContain('OK')
        ->and($rendu)->not->toContain('ÉCHEC')
        ->and($rendu)->toContain('Planifier');
});

it('nomme les configurations écrites, et dit celles qui ne l\'ont pas été', function () {
    $ecrite = ChecklistItem::task('server.config', 'Durcir', 'Un corps.', files: [
        new ChecklistFile('Nginx', 'add_header …', '/site/storage/app/baobab/baobab-nginx.conf'),
    ]);

    $refusee = ChecklistItem::task('server.config', 'Durcir', 'Un corps.', files: [
        new ChecklistFile('Apache', 'Header set …'),
    ]);

    expect(sortie([$ecrite]))->toContain('/site/storage/app/baobab/baobab-nginx.conf')
        ->and(sortie([$refusee]))->toContain('non écrite');
});

/**
 * Ces textes viennent d'une action, pas d'un gabarit de console. Un `<` y
 * serait lu par Symfony comme une balise de style : il disparaîtrait de
 * l'écran, ou laisserait le reste de la ligne colorée.
 */
it('n\'ouvre pas de balise de style avec un texte venu d\'ailleurs', function () {
    expect(sortie([ChecklistItem::task('x', 'Titre <bleu>', 'Corps <vert> aussi.')]))
        ->toContain('Titre <bleu>')
        ->and(sortie([ChecklistItem::task('x', 'Titre', 'Corps <vert> aussi.')]))
        ->toContain('<vert>');
});

it('reste muet en mode non interactif, comme tout le reste de cette sortie', function () {
    $tampon = new BufferedOutput;
    $ui = new InstallerOutput(new OutputStyle(new ArrayInput([]), $tampon), quiet: true);

    $ui->checklist([ChecklistItem::task('cron.scheduler', 'Planifier', 'Un corps.')]);

    expect($tampon->fetch())->toBe('');
});
