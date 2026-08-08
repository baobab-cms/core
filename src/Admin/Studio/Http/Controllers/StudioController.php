<?php

declare(strict_types=1);

namespace Baobab\Admin\Studio\Http\Controllers;

use Baobab\Studio\Actions\CreateModuleBlueprintDraft;
use Baobab\Studio\Actions\DeleteModuleBlueprintDraft;
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
        $blueprint = $draft instanceof ModuleBlueprintDraft ? $draft->blueprint : [];

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
        $values = $handler->initialValues($draft->blueprint);

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
