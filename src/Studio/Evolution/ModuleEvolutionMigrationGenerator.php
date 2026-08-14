<?php

declare(strict_types=1);

namespace Baobab\Studio\Evolution;

use Baobab\ContentTypes\Generator\ColumnNullability;
use Baobab\ContentTypes\Generator\GeneratedFileChecksums;
use Baobab\ContentTypes\Generator\MigrationTimestamp;
use Baobab\ContentTypes\Generator\StubRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Transforme le plan d'une table (`ModuleSchemaReconciler`) en migration
 * incrémentale sur disque.
 *
 * Le nom reste **horodaté**, contrairement aux migrations de création que le
 * n° 120 a fait nommer par le rang dans le graphe : une migration d'évolution
 * est à chaque fois réellement nouvelle, et réutiliser son nom réécrirait une
 * migration déjà jouée — ce que la règle « jamais de modification d'une
 * migration publiée » interdit. `MigrationFilename` documente déjà cette
 * frontière ; elle vaut ici aussi. L'horodatage la place par ailleurs après les
 * migrations de création, dont le préfixe synthétique est `0001_01_01_`.
 *
 * Une seule exception, et elle ne contredit pas la règle : une migration écrite
 * mais **jamais jouée** n'a rien de publié. On la réécrit plutôt que d'en
 * empiler une seconde — sans quoi deux régénérations d'affilée avant un
 * `migrate` produiraient deux fois le même `ADD COLUMN`, dont le second
 * échouerait.
 */
final class ModuleEvolutionMigrationGenerator
{
    private const DIRECTORY = 'database/migrations';

    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly GeneratedFileChecksums $checksums,
    ) {}

    /**
     * @param  array{
     *     table: string,
     *     added: list<array{name: string, definition: string, nullable: bool}>,
     *     dropped: list<array{name: string, definition: string}>,
     *     renamed: list<array{from: string, to: string}>,
     *     retightened: list<array{name: string, definition: string, nullable: bool, nulls: int}>,
     * }  $plan
     * @return string Chemin relatif au module de la migration écrite.
     */
    public function generate(array $plan, string $moduleDir): string
    {
        $table = $plan['table'];

        $up = [];
        $down = [];

        // Les renommages d'abord : une colonne qui prend le nom d'une autre doit
        // l'avoir libéré avant qu'un ajout ne le réclame.
        foreach ($plan['renamed'] as $rename) {
            $up[] = $this->line("\$table->renameColumn('{$rename['from']}', '{$rename['to']}');");
            $down[] = $this->line("\$table->renameColumn('{$rename['to']}', '{$rename['from']}');");
        }

        foreach ($plan['added'] as $column) {
            $up[] = $this->line($column['definition']);
            $down[] = $this->line("\$table->dropColumn('{$column['name']}');");
        }

        foreach ($plan['retightened'] as $column) {
            $up[] = $this->line($this->asChange($column['definition']));
            // L'état d'origine est l'exact inverse de la nullabilité visée —
            // `apply()` sert donc ici à la retourner, pas à l'appliquer.
            $down[] = $this->line($this->asChange(ColumnNullability::apply($column['definition'], $column['nullable'])));
        }

        foreach ($plan['dropped'] as $column) {
            $up[] = $this->line("\$table->dropColumn('{$column['name']}');");
            $down[] = $this->line($column['definition']);
        }

        $contents = $this->renderer->render(StubRenderer::stubPath('evolution-migration'), [
            'table_name' => $table,
            'up_statements' => implode("\n", $up),
            'down_statements' => implode("\n", array_reverse($down)),
        ]);

        $filename = $this->targetFile($moduleDir, $table);
        $this->checksums->write($moduleDir, $filename, $contents, true);

        return $filename;
    }

    /**
     * Réutilise la migration d'évolution de cette table qui attend encore d'être
     * jouée, s'il y en a une ; sinon, un nom neuf.
     *
     * L'écriture se fait en `overwrite` assumé : le fichier réutilisé est
     * toujours un fichier que cette classe vient d'écrire elle-même quelques
     * instants plus tôt, et son checksum ne peut différer que si l'utilisateur
     * l'a édité à la main — auquel cas la version éditée décrit un état de
     * schéma qui n'a pas été appliqué et que le plan courant recalcule.
     */
    private function targetFile(string $moduleDir, string $table): string
    {
        $suffix = "_evolve_{$table}_table.php";
        $directory = rtrim($moduleDir, '/\\').'/'.self::DIRECTORY;

        foreach ($this->pendingFiles($directory, $suffix) as $pending) {
            return self::DIRECTORY.'/'.$pending;
        }

        return self::DIRECTORY.'/'.MigrationTimestamp::generate().$suffix;
    }

    /**
     * Fichiers d'évolution présents sur disque dont la table `migrations`
     * n'a pas trace — donc jamais exécutés.
     *
     * @return list<string>
     */
    private function pendingFiles(string $directory, string $suffix): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        $ran = DB::table('migrations')->pluck('migration')->all();

        $pending = [];

        foreach (File::files($directory) as $file) {
            $basename = $file->getFilename();

            if (! str_ends_with($basename, $suffix)) {
                continue;
            }

            if (! in_array(substr($basename, 0, -4), $ran, true)) {
                $pending[] = $basename;
            }
        }

        sort($pending);

        return $pending;
    }

    /**
     * `->change()` ne porte que sur la définition de la colonne : un index y est
     * un objet distinct, que Laravel ne modifie pas et qu'un `->unique()`
     * restaté tenterait de recréer. On le retire donc de la ligne — l'index déjà
     * en base, lui, reste en place.
     */
    private function asChange(string $definition): string
    {
        return rtrim(str_replace('->unique()', '', $definition), ';').'->change();';
    }

    private function line(string $statement): string
    {
        return '            '.trim($statement);
    }
}
