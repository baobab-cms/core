<?php

declare(strict_types=1);

/**
 * Garde de conformité des vues du Core (suivi n° 138).
 *
 * La règle « les contrôleurs calculent, les vues affichent » était appliquée
 * sans être vérifiée : le seul garde existant vivait dans la suite du package
 * du thème par défaut (M8 point 10, Pass B3) et ne regardait pas les vues du
 * Core, qui n'en avaient aucun. Sept vues avaient dérivé.
 *
 * Le garde **retire les commentaires Blade avant de chercher** — sans quoi il
 * accuserait une vue qui se contente de *citer* la règle. Ce n'est pas une
 * précaution théorique : l'inventaire d'origine du n° 138 a compté `wizard`
 * parmi les fautives précisément pour cette raison, alors que son commentaire
 * disait « pas de `@php` ici ».
 */
/**
 * @return list<string>
 */
function baobabCoreBladeFiles(): array
{
    $root = realpath(__DIR__.'/../../resources/views');

    if ($root === false) {
        throw new RuntimeException('Répertoire des vues du Core introuvable.');
    }

    $files = [];

    /** @var iterable<string, SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Le source d'une vue, commentaires Blade retirés.
 */
function baobabViewSourceWithoutComments(string $path): string
{
    $source = file_get_contents($path);

    if ($source === false) {
        throw new RuntimeException(sprintf('Vue illisible : %s', $path));
    }

    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
}

it('trouve les vues du Core', function () {
    // Sans cette assertion, une erreur de chemin rendrait les gardes suivants
    // verts sur un ensemble vide — le pire des faux négatifs.
    expect(baobabCoreBladeFiles())->not->toBeEmpty();
});

it('ne laisse aucun bloc @php dans les vues du Core', function () {
    $offenders = [];

    foreach (baobabCoreBladeFiles() as $path) {
        if (preg_match('/(?:^|\s)@php\b/', baobabViewSourceWithoutComments($path)) === 1) {
            $offenders[] = str_replace('\\', '/', substr($path, (int) strpos($path, 'resources')));
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Ces vues du Core portent de la logique dans un bloc @php :\n- %s\n".
        "Le calcul appartient au contrôleur, ou au composant de classe quand il s'agit d'un composant.",
        implode("\n- ", $offenders)
    ));
});

it('ignore une mention de @php écrite dans un commentaire Blade', function () {
    $file = tempnam(sys_get_temp_dir(), 'baobab-view-guard-').'.blade.php';

    file_put_contents($file, "{{-- Pas de @php ici, jamais. --}}\n<p>Rien</p>\n");

    $stripped = baobabViewSourceWithoutComments($file);

    expect(preg_match('/(?:^|\s)@php\b/', $stripped))->toBe(0);

    unlink($file);
});
