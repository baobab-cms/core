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

    public function __invoke(ModuleBlueprintDraft $draft): ModuleBlueprintDraft
    {
        $blueprint = ModuleBlueprint::fromJson((string) json_encode($draft->migratedBlueprint()));

        $name = ($this->generator)($blueprint);

        ($this->install)($name);
        $module = ($this->activate)($name);

        $draft->module_id = $module->id;
        $draft->generated_at = now();
        $draft->save();

        $this->audit->record('studio.draft.generated', $draft, ['module' => $name]);

        return $draft;
    }
}
