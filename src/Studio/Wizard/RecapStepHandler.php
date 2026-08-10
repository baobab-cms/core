<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Models\ModuleBlueprintDraft;

/**
 * Étape 9 — Récapitulatif & génération (spec-modules §5.2 étape 9).
 *
 * **Étape sans saisie**, comme l'étape 5 : elle n'écrit rien dans le
 * blueprint. Sa soumission ne franchit même pas une étape de plus — c'est la
 * dernière — et la génération elle-même passe par une route dédiée
 * (`StudioController::generate()`), pas par le cycle `SaveStudioWizardStep` :
 * générer n'est pas enregistrer une étape.
 *
 * L'aperçu vient de `ModuleGenerator::plan()`, **la carte même que la
 * génération déroulera** (extraite en Pass B5) : ce que l'écran montre n'est
 * pas une description de ce qui sera écrit, c'est ce qui sera écrit.
 */
final class RecapStepHandler implements StudioStepHandler
{
    public function __construct(private readonly ModuleGenerator $generator) {}

    public function number(): int
    {
        return 9;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.recap');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.recap';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [];
    }

    public function fill(array $validated, array $blueprint): array
    {
        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        return [];
    }

    /**
     * Deux cas seulement, et l'écran doit les distinguer nettement :
     *
     * - le blueprint est générable → l'arborescence complète et le contenu de
     *   chaque fichier, plus le répertoire cible ;
     * - il ne l'est pas → le message de la validation stricte, tel quel. Le
     *   même contrôle qu'à la génération est donc joué **à l'affichage**,
     *   pour que l'utilisateur voie le problème avant de cliquer plutôt
     *   qu'après. C'est aussi ce qui rattrape la seule combinaison que le
     *   brouillon laisse volontairement passer (`auto_crud: false` × surface
     *   autorisée, suivi n° 103).
     */
    public function viewData(array $blueprint): array
    {
        $name = (string) ($blueprint['identity']['name'] ?? '');

        try {
            $validated = ModuleBlueprint::fromJson((string) json_encode($blueprint));
        } catch (InvalidModuleBlueprintException $e) {
            return [
                'files' => [],
                'tree' => [],
                'moduleDir' => null,
                'blueprintError' => $e->getMessage(),
            ];
        }

        $files = $this->generator->plan($validated);
        ksort($files);

        return [
            'files' => $files,
            'tree' => $this->groupByDirectory(array_keys($files)),
            'moduleDir' => $this->generator->moduleDir($name),
            'blueprintError' => null,
        ];
    }

    /**
     * Arborescence prête à rendre : répertoire → noms de fichiers. Calculée
     * ici et pas dans la vue (« pas de logique dans les vues admin »), et
     * dérivée des seules clés du plan — aucune liste tenue à la main.
     *
     * @param  list<string>  $paths
     * @return array<string, list<string>>
     */
    private function groupByDirectory(array $paths): array
    {
        $tree = [];

        foreach ($paths as $path) {
            $directory = str_contains($path, '/') ? dirname($path) : '.';
            $tree[$directory][] = basename($path);
        }

        ksort($tree);

        return $tree;
    }

    public function valuesFromOldInput(array $old, array $values): array
    {
        return $values;
    }
}
