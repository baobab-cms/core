<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\SyncModuleManifest;
use Baobab\Actions\Modules\UninstallModule;
use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Exceptions\GeneratedFileConflictException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Support\ModuleMigrations;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Exceptions\ModuleGenerationFailedException;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Support\Logger;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Génère, installe et active le module décrit par un brouillon
 * (spec-modules §5.2 étape 9, §5.3 « Générer dans `/modules` »).
 *
 * Trois choses en un seul point, dans cet ordre, parce qu'elles n'ont de sens
 * qu'ensemble du point de vue de l'utilisateur du wizard : un module généré
 * mais ni installé ni activé n'apparaîtrait nulle part dans l'admin, ce qui
 * était précisément l'incompréhension signalée en Pass B1 (suivi n° 99). La
 * CLI `module:build` garde son comportement inverse et volontaire — générer
 * seulement, installer/activer étant des commandes distinctes : au terminal,
 * l'enchaînement se compose ; dans un wizard, il se termine.
 *
 * **Le blueprint est ici validé strictement** (`fromJson()`, et non
 * `fromDraftJson()` qui sert pendant la saisie) : c'est le seul moment où le
 * schéma complet et les cross-validations de génération s'appliquent, dont le
 * refus de la combinaison `auto_crud: false` × surface autorisée (décision du
 * 9 août 2026, suivi n° 103).
 *
 * `InvalidModuleBlueprintException` (blueprint incomplet ou incohérent) et
 * `GeneratedFileConflictException` (fichier généré modifié à la main depuis la
 * dernière génération) remontent **telles quelles** : leurs messages sont déjà
 * écrits pour un humain, et les envelopper les rendrait moins précises. Tout le
 * reste — échec d'écriture, d'installation, de migration, d'activation — est
 * enveloppé dans `ModuleGenerationFailedException`, qui nomme l'étape et ne
 * porte ni SQL ni trace : la persona du Studio est explicitement
 * non-technicienne, et la page d'exception de Laravel affichait jusqu'au
 * manifeste entier (suivi n° 147).
 *
 * **Et l'enveloppe ne suffisait pas.** Un échec entre l'installation et
 * l'enregistrement du brouillon laissait un module actif devant un brouillon
 * qui l'ignorait ; la soumission suivante réinstallait et heurtait
 * `modules_name_unique` — l'erreur signalée le 14 août 2026. Chaque échec de
 * première génération est donc désormais **compensé** avant d'être présenté,
 * de sorte que la relance soit possible telle quelle (n° 148). Le brouillon
 * n'est marqué généré que si toute la chaîne a réussi.
 */
final class GenerateModuleFromDraft
{
    public function __construct(
        private readonly ModuleGenerator $generator,
        private readonly InstallModule $install,
        private readonly ActivateModule $activate,
        private readonly EvolveModuleSchema $evolveSchema,
        private readonly SyncModuleManifest $syncManifest,
        private readonly AuditLogger $audit,
        private readonly UninstallModule $uninstall,
        private readonly ModuleMigrations $migrations,
        private readonly Logger $logger,
    ) {}

    /**
     * Première génération **ou** régénération d'un brouillon déjà généré
     * (spec-modules §5.4, « rouvrir un blueprint, régénérer »).
     *
     * Une régénération **ne réinstalle ni ne réactive** : le module est déjà
     * installé et actif. Elle réécrit les fichiers, **puis aligne la base sur
     * eux** (`EvolveModuleSchema`, suivi n° 111) — une entité ajoutée après coup
     * voit désormais sa table créée, et une colonne fausse depuis l'origine
     * corrigée (n° 137). Les deux moitiés vivent dans le même appel parce
     * qu'elles n'ont de sens qu'ensemble : des fichiers qui décrivent un schéma
     * que la base ne porte pas ne sont pas une régénération réussie.
     *
     * L'ordre compte, et il est l'inverse de l'intuition : on écrit les fichiers
     * **avant** de migrer, parce que la migration de création d'une entité
     * nouvelle est l'un des fichiers écrits.
     *
     * L'évolution du schéma **n'est pas tentée à la première génération** : il
     * n'y a alors rien à faire évoluer, `InstallModule` jouant lui-même les
     * migrations du module — et il capture au passage le manifeste, ce qui vaut
     * pour la resynchronisation la même dispense.
     *
     * `$overwrite` : chemins dont l'utilisateur a explicitement accepté
     * l'écrasement après avoir vu le diff. **Un conflit non accepté n'arrête
     * pas la régénération** : le fichier est laissé intact et signalé dans
     * `skipped`, conformément à la spec 01 §5.4 (« régénérer uniquement les
     * nouveaux fichiers, jamais d'écrasement silencieux »). C'est à
     * l'appelant de décider s'il veut d'abord montrer le diff — cette Action,
     * elle, ne bloque jamais.
     *
     * @param  list<string>  $overwrite
     * @return array{
     *     written: list<string>,
     *     skipped: list<string>,
     *     schema?: array{migrations: list<string>, created: list<string>, changes: int},
     *     manifest?: array{manifest_changed: bool, permissions: array{added: list<string>, updated: list<string>, removed: list<string>}, menu_items: int},
     * }
     */
    public function __invoke(ModuleBlueprintDraft $draft, array $overwrite = [], bool $confirmDestructive = false): array
    {
        $blueprint = ModuleBlueprint::fromJson((string) json_encode($draft->migratedBlueprint()));

        $wasGenerated = $draft->isGenerated();
        $name = (string) $blueprint->name();

        // Ce que la compensation a le droit de détruire, relevé **avant** la
        // moindre écriture. Sans ces deux témoins, un nom déjà pris — le cas
        // même de l'incident du 14 août — ferait désinstaller le module de
        // quelqu'un d'autre en croyant annuler le sien : une compensation plus
        // destructrice que le défaut qu'elle répare.
        $preexisting = new GenerationPreexistingState(
            moduleRow: Module::where('name', $name)->exists(),
            moduleDirectory: File::isDirectory($this->generator->moduleDir($name)),
        );

        try {
            $result = $this->generator->write($blueprint, $overwrite);
        } catch (GeneratedFileConflictException|InvalidModuleBlueprintException $e) {
            // Les deux exceptions que l'appelant sait déjà présenter : les
            // envelopper les rendrait moins précises, pas plus.
            throw $e;
        } catch (Throwable $e) {
            // Rien n'est encore en base à ce stade. Des fichiers ont pu être
            // écrits avant l'échec ; ils sont retirés par la compensation
            // comme les autres, sur une première génération seulement.
            $this->rollbackFirstGeneration($wasGenerated, $name, $preexisting);

            throw ModuleGenerationFailedException::whileWritingFiles($name, $e);
        }

        if (! $wasGenerated) {
            try {
                ($this->install)($name);
            } catch (Throwable $e) {
                $this->rollbackFirstGeneration($wasGenerated, $name, $preexisting);

                throw ModuleGenerationFailedException::whileInstalling($name, $e);
            }

            try {
                $module = ($this->activate)($name);
            } catch (Throwable $e) {
                $this->rollbackFirstGeneration($wasGenerated, $name, $preexisting);

                throw ModuleGenerationFailedException::whileActivating($name, $e);
            }

            $draft->module_id = $module->id;
        } else {
            $result['schema'] = ($this->evolveSchema)($draft, $blueprint, $confirmDestructive);

            // Puis ce que le Core sait du module : permissions, menus, hooks,
            // widgets… tous capturés à l'installation et jamais relus (n° 95).
            // Après l'évolution du schéma, et non avant : une permission qui
            // gouverne un écran dont la table n'existe pas encore serait vraie
            // un instant trop tôt.
            $module = $draft->module;

            if ($module !== null) {
                $result['manifest'] = ($this->syncManifest)($module, $confirmDestructive);
            }
        }

        // L'instantané n'est enregistré qu'une fois toute la chaîne passée : il
        // affirme « voici ce qui est sur disque et en base », et une évolution
        // refusée (suppression non confirmée, colonne à remplir) ne doit pas
        // laisser croire que l'état visé a été atteint.
        $draft->generated_blueprint = $blueprint->toArray();
        $draft->generated_at = now();
        $draft->save();

        $this->audit->record('studio.draft.generated', $draft, [
            'module' => $name,
            'written' => count($result['written']),
            'skipped' => $result['skipped'],
            'overwritten' => $overwrite,
        ]);

        return $result;
    }

    /**
     * Ramène le système à l'état d'avant le clic, pour que la relance soit
     * possible telle quelle — l'objectif produit n'est pas « ne rien casser »,
     * c'est « corriger et relancer sans l'erreur ».
     *
     * **Première génération seulement**, et la restriction est vitale : sur une
     * régénération, `deleteFiles` effacerait un module qui existait avant et
     * fonctionnait. Une régénération qui échoue laisse donc ses fichiers écrits
     * et remonte l'erreur — écart borné et assumé (suivi n° 203), le module
     * restant installé et opérant.
     *
     * **Pourquoi une compensation et non une transaction** : `InstallModule`
     * joue les migrations **hors** de toute transaction, un `CREATE TABLE`
     * provoquant un commit implicite sur MySQL/MariaDB qui casserait
     * silencieusement une transaction englobante. `DB::transaction()` autour de
     * la chaîne n'est pas insuffisant ici, il est impossible.
     *
     * `UninstallModule` fait déjà exactement le travail — rollback des
     * migrations, ligne `modules` supprimée, permissions et menus par cascade,
     * fichiers retirés — mais refuse un module actif : d'où la désactivation
     * préalable, silencieuse puisque le module peut n'avoir jamais été activé.
     */
    private function rollbackFirstGeneration(bool $wasGenerated, string $name, GenerationPreexistingState $preexisting): void
    {
        if ($wasGenerated) {
            return;
        }

        $moduleDir = $this->generator->moduleDir($name);

        try {
            // Cas 1 — la ligne `modules` est la nôtre : elle n'existait pas
            // avant, elle existe maintenant. `UninstallModule` fait alors tout
            // le travail d'un coup, et il refuse un module actif — d'où la
            // remise à `installed` sans condition, le module pouvant l'être
            // comme ne l'avoir jamais été.
            if (! $preexisting->moduleRow && Module::where('name', $name)->exists()) {
                Module::where('name', $name)->update(['status' => 'installed']);

                ($this->uninstall)($name, purge: true, deleteFiles: true);

                return;
            }

            if ($preexisting->moduleRow) {
                // Le nom était déjà pris — c'est précisément ce qui a déclenché
                // l'incident d'origine. La ligne appartient à quelqu'un
                // d'autre : on n'y touche pas. Ce qu'on a écrit, en revanche,
                // reste à nous et part ci-dessous.
                $this->logger->warning('Génération Studio annulée : le nom était déjà pris, le module homonyme est laissé intact.', ['module' => $name]);
            }

            // Cas 2 — pas de ligne à nous, mais nos migrations ont pu tourner :
            // `InstallModule` les joue **avant** de créer la ligne, si bien
            // qu'un échec à la création laisse des tables derrière lui et rien
            // en base pour les désigner. Les défaire par le chemin est la seule
            // voie, et c'est ce qui rend « tout a été annulé » vrai plutôt que
            // rassurant.
            $this->migrations->rollback($moduleDir);

            // Le répertoire ne part que s'il n'existait pas avant : un dépôt
            // manuel, ou les restes d'une tentative que l'utilisateur voulait
            // garder, ne sont pas à nous.
            if (! $preexisting->moduleDirectory) {
                File::deleteDirectory($moduleDir);
            }
        } catch (Throwable $e) {
            // L'échec de l'annulation ne remplace pas l'échec d'origine dans
            // le journal, il s'y ajoute : les deux comptent pour comprendre.
            $this->logger->error('Annulation d\'une génération Studio échouée.', [
                'module' => $name,
                'error' => $e->getMessage(),
            ]);

            throw ModuleGenerationFailedException::afterFailedRollback($name, $e);
        }
    }
}
