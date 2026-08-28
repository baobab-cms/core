<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\Exceptions\InstallationStepFailed;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * Étape 3 de l'installation (spec 15 §4) : les migrations, et rien de plus.
 *
 * **Il n'y a pas de seeder à appeler ici**, contrairement à ce que la spec
 * annonçait jusqu'au 25 août 2026 (suivi n° 212) : les rôles et permissions
 * par défaut sont posés par des migrations — `seed_default_roles`,
 * `grant_admin_access_to_default_roles`. Une migration s'exécute toujours ;
 * un seeder demande à être appelé, ce qu'aucune installation par archive ne
 * garantit.
 *
 * `--force` est indispensable et non un raccourci : `migrate` refuse de
 * s'exécuter sans confirmation en environnement de production, et une
 * installation graphique n'a personne pour répondre à une invite console.
 */
final class RunMigrations
{
    public function __construct(private readonly Kernel $artisan) {}

    /**
     * Rend les migrations réellement passées, dans l'ordre — suivi n° 224.
     *
     * **Pourquoi une liste plutôt qu'un code de retour.** Le wizard graphique
     * montre l'avancement étape par étape, et l'étape des migrations est la
     * seule assez longue pour qu'on ait envie de savoir ce qu'elle a fait.
     * `direction-visuelle.md` le demande — « on regarde Baobab écrire de vrais
     * fichiers », « ne pas le remplacer par une barre abstraite » — et la seule
     * façon honnête de le tenir ici est de dire, une fois l'étape finie, ce
     * qu'elle a réellement passé.
     *
     * **Lecture de la sortie plutôt qu'écoute d'événements.** `MigrationEnded`
     * transporte l'objet migration, or une migration anonyme — la forme que
     * Laravel génère depuis la 9 — n'a pas de nom à donner. Le nom lisible
     * n'existe que dans la sortie du migrateur, qui le tire du fichier.
     *
     * **L'analyse ne peut pas faire échouer l'installation** : si le format de
     * sortie change un jour, la liste sera vide et le wizard affichera l'étape
     * sans détail. Un ornement ne doit jamais casser ce qu'il décore.
     *
     * @return list<string>
     */
    public function __invoke(): array
    {
        try {
            $status = $this->artisan->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw InstallationStepFailed::migrations(
                'La création des tables a échoué. La base est joignable, mais l\'utilisateur '
                .'qui s\'y connecte n\'a peut-être pas le droit de créer des tables — '
                .'vérifiez ses privilèges auprès de votre hébergement.',
                $e,
            );
        }

        if ($status !== 0) {
            throw InstallationStepFailed::migrations(
                'La création des tables s\'est interrompue. Relancez l\'installation : '
                .'elle reprendra à cette étape sans refaire les précédentes.',
            );
        }

        return $this->migrationsFrom($this->artisan->output());
    }

    /**
     * Extrait les noms de migrations d'une sortie de `migrate`.
     *
     * Le migrateur écrit une ligne par migration, du genre
     * `2026_07_10_000006_seed_default_roles ......... 12.34ms DONE`. On retient
     * le premier mot des lignes qui se terminent par un verdict, et rien
     * d'autre : ni les en-têtes, ni les lignes de tableau, ni « Nothing to
     * migrate ».
     *
     * @return list<string>
     */
    private function migrationsFrom(string $output): array
    {
        $migrations = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^\s*(\d{4}_\d{2}_\d{2}_\d{6}_\S+)\s+.*\b(DONE|FAIL)\b/u', $line, $matches) === 1) {
                $migrations[] = $matches[1];
            }
        }

        return $migrations;
    }
}
