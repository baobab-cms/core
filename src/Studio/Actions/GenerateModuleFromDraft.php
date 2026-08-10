<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Audit\AuditLogger;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Models\ModuleBlueprintDraft;

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
 * Aucune capture d'exception : `InvalidModuleBlueprintException` (blueprint
 * incomplet ou incohérent) et `GeneratedFileConflictException` (fichier généré
 * modifié à la main depuis la dernière génération) remontent telles quelles à
 * l'appelant, qui sait seul comment les présenter. Le brouillon n'est marqué
 * généré que si toute la chaîne a réussi.
 */
final class GenerateModuleFromDraft
{
    public function __construct(
        private readonly ModuleGenerator $generator,
        private readonly InstallModule $install,
        private readonly ActivateModule $activate,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Première génération **ou** régénération d'un brouillon déjà généré
     * (spec-modules §5.4, « rouvrir un blueprint, régénérer »).
     *
     * Une régénération **ne réinstalle ni ne réactive** : le module est déjà
     * installé et actif, elle ne fait que réécrire les fichiers. Conséquence
     * assumée et à connaître : une entité *ajoutée* après coup produit bien sa
     * migration sur le disque, mais celle-ci n'est pas exécutée — faire
     * évoluer le schéma d'un module installé est un sujet distinct, l'équivalent
     * pour les modules de ce que `EvolveContentType` +
     * `EvolutionMigrationGenerator` font pour un Content Type ; hors périmètre
     * de cette passe et consigné.
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
     * @return array{written: list<string>, skipped: list<string>}
     */
    public function __invoke(ModuleBlueprintDraft $draft, array $overwrite = []): array
    {
        $blueprint = ModuleBlueprint::fromJson((string) json_encode($draft->migratedBlueprint()));

        $result = $this->generator->write($blueprint, $overwrite);
        $name = (string) $blueprint->name();

        if (! $draft->isGenerated()) {
            ($this->install)($name);
            $module = ($this->activate)($name);

            $draft->module_id = $module->id;
        }

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
}
