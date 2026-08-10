<?php

declare(strict_types=1);

namespace Baobab\Admin\Studio\Http\Controllers;

use Baobab\Studio\Actions\CreateModuleBlueprintDraft;
use Baobab\Studio\Actions\DeleteModuleBlueprintDraft;
use Baobab\Studio\Actions\GenerateModuleFromDraft;
use Baobab\Studio\Actions\InspectModuleConflicts;
use Baobab\Studio\Actions\PackageModuleFromDraft;
use Baobab\Studio\Actions\SaveStudioWizardStep;
use Baobab\Studio\Exceptions\GeneratedDraftCannotBeDeletedException;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Wizard\StudioStepHandler;
use Baobab\Studio\Wizard\StudioWizardSteps;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shell du Wizard Studio (spec-modules §5.2, Pass B1) — accès gouverné par
 * `baobab.system.studio.manage` (routes/admin.php), pas de vérification
 * d'instance supplémentaire ici : patron `WidgetsController`/
 * `BrandingController` (permission plate, aucune policy own/any pour cet
 * outillage de développeur).
 */
final class StudioController
{
    public function __construct(
        private readonly StudioWizardSteps $steps,
        private readonly Factory $views,
    ) {}

    public function index(): View
    {
        return view('baobab::admin.studio.index', [
            'drafts' => ModuleBlueprintDraft::orderByDesc('updated_at')->get(),
        ]);
    }

    public function create(): View
    {
        $handler = $this->steps->find(1);
        abort_if($handler === null, 500, "L'étape 1 (Identité) n'est pas enregistrée.");

        return $this->renderStep($handler, [
            'draft' => null,
            'handler' => $handler,
            'values' => $handler->initialValues([]),
            'steps' => $this->navSteps(null, 1),
            'formAction' => route('admin.studio.store'),
            'isLastImplementedStep' => $this->steps->lastImplemented() === 1,
        ]);
    }

    public function store(Request $request, CreateModuleBlueprintDraft $action): RedirectResponse
    {
        $handler = $this->steps->find(1);
        abort_if($handler === null, 500, "L'étape 1 (Identité) n'est pas enregistrée.");

        $validated = $request->validate($handler->rules(new ModuleBlueprintDraft));

        $draft = $action($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.studio.draft_created')]);

        return redirect()->route('admin.studio.step.show', [$draft, 1]);
    }

    public function show(ModuleBlueprintDraft $draft): RedirectResponse
    {
        return redirect()->route('admin.studio.step.show', [$draft, $draft->current_step]);
    }

    public function stepShow(ModuleBlueprintDraft $draft, int $step): View
    {
        $handler = $this->steps->find($step);
        abort_if($handler === null || $step > $draft->current_step, 404);

        return $this->renderStep($handler, [
            'draft' => $draft,
            'handler' => $handler,
            'values' => $this->stepValues($handler, $draft),
            'steps' => $this->navSteps($draft, $step),
            'formAction' => route('admin.studio.step.update', [$draft, $step]),
            'isLastImplementedStep' => $step === $this->steps->lastImplemented(),
        ]);
    }

    public function stepUpdate(ModuleBlueprintDraft $draft, int $step, Request $request, SaveStudioWizardStep $action): RedirectResponse
    {
        $handler = $this->steps->find($step);
        abort_if($handler === null || $step > $draft->current_step, 404);

        $validated = $request->validate($handler->rules($draft));

        try {
            $draft = $action($draft, $handler, $validated);
        } catch (InvalidModuleBlueprintException $e) {
            // Le blueprint reconstruit ne passe pas la cross-validation du
            // moteur (type de champ inconnu, cible de relation absente,
            // `choices` manquant sur un select…). Patron `MenusController` :
            // on renvoie le message tel quel, il porte déjà le chemin précis
            // du champ fautif. `withInput()` est ce sur quoi s'appuie
            // `stepValues()` au ré-affichage pour ne pas perdre la saisie.
            return back()->withInput()->withErrors(['blueprint' => $e->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.studio.step_saved')]);

        $nextStep = $step + 1;

        if ($this->steps->has($nextStep) && $nextStep <= $draft->current_step) {
            return redirect()->route('admin.studio.step.show', [$draft, $nextStep]);
        }

        return redirect()->route('admin.studio.step.show', [$draft, $step]);
    }

    /**
     * Génération, ou régénération d'un brouillon déjà généré (spec-modules
     * §5.2 étape 9, §5.3 ; spec 01 §5.4 pour les conflits).
     *
     * Deux entrées, distinguées par le drapeau `resolved` que seul l'écran de
     * conflit envoie :
     *
     * - depuis le récapitulatif, si des fichiers générés ont été modifiés à la
     *   main, on **détourne vers le diff** avant d'écrire quoi que ce soit —
     *   l'utilisateur doit voir ce qu'il risque de perdre ;
     * - depuis l'écran de conflit, le choix est fait : on régénère ce qui est
     *   sûr, on écrase ce qui a été coché, et on **laisse intact** le reste.
     *   C'est bien « régénérer uniquement les nouveaux fichiers », pas
     *   « tout ou rien ».
     */
    public function generate(Request $request, ModuleBlueprintDraft $draft, GenerateModuleFromDraft $action, InspectModuleConflicts $inspect): RedirectResponse
    {
        $validated = $request->validate([
            'overwrite' => ['nullable', 'array'],
            'overwrite.*' => ['string'],
            'resolved' => ['nullable', 'boolean'],
        ]);

        /** @var list<string> $overwrite */
        $overwrite = $validated['overwrite'] ?? [];

        try {
            if (! ($validated['resolved'] ?? false) && $inspect($draft) !== []) {
                return redirect()->route('admin.studio.conflicts', $draft);
            }

            $result = $action($draft, $overwrite);
        } catch (InvalidModuleBlueprintException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.studio.step.show', [$draft, 9]);
        }

        session()->flash('toast', $result['skipped'] === []
            ? ['type' => 'success', 'message' => __('baobab::admin.studio.recap.generated_files', ['count' => count($result['written'])])]
            : ['type' => 'warning', 'message' => __('baobab::admin.studio.recap.generated_files_kept', [
                'count' => count($result['written']),
                'kept' => count($result['skipped']),
            ])]);

        return redirect()->route('admin.studio.step.show', [$draft, 9]);
    }

    /**
     * Écran de résolution : ce que la régénération écraserait, fichier par
     * fichier, avec son diff. L'utilisateur coche ce qu'il accepte de perdre —
     * rien n'est écrasé par défaut.
     */
    public function conflicts(ModuleBlueprintDraft $draft, InspectModuleConflicts $inspect): View|RedirectResponse
    {
        try {
            $conflicts = $inspect($draft);
        } catch (InvalidModuleBlueprintException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.studio.step.show', [$draft, 9]);
        }

        if ($conflicts === []) {
            return redirect()->route('admin.studio.step.show', [$draft, 9]);
        }

        return view('baobab::admin.studio.conflicts', [
            'draft' => $draft,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Téléchargement de l'archive (spec-modules §5.3). `deleteFileAfterSend()`
     * plutôt qu'un nettoyage différé : l'archive est un fichier temporaire créé
     * pour cette réponse et pour elle seule.
     */
    public function download(ModuleBlueprintDraft $draft, PackageModuleFromDraft $action): BinaryFileResponse|RedirectResponse
    {
        try {
            $archivePath = $action($draft);
        } catch (InvalidModuleBlueprintException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.studio.step.show', [$draft, 9]);
        }

        return response()
            ->download($archivePath, basename($archivePath))
            ->deleteFileAfterSend();
    }

    public function destroy(ModuleBlueprintDraft $draft, DeleteModuleBlueprintDraft $action): RedirectResponse
    {
        try {
            $action($draft);
        } catch (GeneratedDraftCannotBeDeletedException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.studio.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.studio.draft_deleted')]);

        return redirect()->route('admin.studio.index');
    }

    /**
     * Patron `TemplateHierarchyResolver::resolve()` : `Factory::exists()`
     * porte `@phpstan-assert-if-true view-string $view` (stub Larastan),
     * seul moyen de faire accepter un nom de vue dynamique (dérivé du handler
     * d'étape, jamais un littéral) à `view()`.
     *
     * @param  array<string, mixed>  $data
     */
    private function renderStep(StudioStepHandler $handler, array $data): View
    {
        $viewName = $handler->view();

        abort_unless($this->views->exists($viewName), 500, "Vue introuvable pour l'étape {$handler->number()}.");

        $draft = $data['draft'];
        // `migratedBlueprint()` et non `blueprint` : c'est le chemin de
        // lecture documenté par le modèle (migrateurs de blueprint appliqués
        // avant toute lecture), et celui qu'emprunte la génération. No-op
        // aujourd'hui, mais l'aperçu de l'étape 9 doit lire exactement ce que
        // la génération lira — sinon la divergence naîtra au premier migrateur.
        $blueprint = $draft instanceof ModuleBlueprintDraft ? $draft->migratedBlueprint() : [];

        return $this->views->make($viewName, [...$handler->viewData($blueprint), ...$data]);
    }

    /**
     * Valeurs du formulaire : le blueprint enregistré, sauf au retour d'une
     * soumission refusée par la cross-validation — l'entrée re-flashée par
     * `stepUpdate()` prime alors, sinon l'utilisateur retrouverait l'écran
     * d'avant sa saisie. Le handler décide seul de ce qu'il sait réhydrater.
     *
     * @return array<string, mixed>
     */
    private function stepValues(StudioStepHandler $handler, ModuleBlueprintDraft $draft): array
    {
        $values = $handler->initialValues($draft->migratedBlueprint());

        if (! session()->hasOldInput()) {
            return $values;
        }

        /** @var array<string, mixed> $old */
        $old = session()->getOldInput();

        return $handler->valuesFromOldInput($old, $values);
    }

    /**
     * @return list<array{number: int, label: string, status: string, url: string|null}>
     */
    private function navSteps(?ModuleBlueprintDraft $draft, int $activeStep): array
    {
        $reached = $draft !== null ? $draft->current_step : 1;
        $lastImplemented = $this->steps->lastImplemented();

        $items = [];

        foreach ($this->steps->labels() as $number => $label) {
            $reachable = $draft !== null && $number <= $reached && $number <= $lastImplemented;

            $items[] = [
                'number' => $number,
                'label' => $label,
                'status' => match (true) {
                    $number === $activeStep => 'current',
                    $number < $activeStep => 'completed',
                    default => 'upcoming',
                },
                'url' => $reachable ? route('admin.studio.step.show', [$draft, $number]) : null,
            ];
        }

        return $items;
    }
}
