<?php

declare(strict_types=1);

namespace Baobab\Admin\Forms\Http\Controllers;

use Baobab\Forms\Actions\DeleteForm;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Exceptions\DuplicateFormSlugException;
use Baobab\Forms\Exceptions\InvalidFormBlueprintException;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Support\FormFieldsNormalizer;
use Baobab\Forms\Support\FormFieldTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Formulaires » (spec 14 §3, M8 point 6). Shell (Pass B1) : liste,
 * création d'un formulaire vide (titre + slug), suppression. Builder de
 * champs (Pass B2) : `edit()`/`update()`, adaptateur mince sur `SaveForm`
 * (Pass A) — le glisser-déposer, la normalisation et la validation du
 * blueprint vivent ailleurs (`field-rows.blade.php`, `FormFieldsNormalizer`,
 * `FormBlueprint`), ce contrôleur ne fait que les enchaîner.
 *
 * Le slug n'est jamais modifiable après création : il identifie le
 * formulaire pour l'endpoint public (Pass C), l'export/import (Pass B4) et
 * les URLs déjà partagées — un choix restreint mais assumé pour cette passe,
 * cohérent avec l'absence de tout mécanisme de redirection d'ancien slug.
 *
 * Accès gouverné par `baobab.system.forms.manage` (routes/admin.php).
 */
final class FormsController
{
    public function index(): View
    {
        $forms = Form::query()
            ->orderBy('title')
            ->paginate(20);

        return view('baobab::admin.forms.index', [
            'forms' => $forms,
            'columns' => $this->columns(),
        ]);
    }

    public function create(): View
    {
        return view('baobab::admin.forms.create');
    }

    public function store(Request $request, SaveForm $action): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255'],
        ]);

        try {
            $action(null, [...$validated, 'fields' => []]);
        } catch (DuplicateFormSlugException $exception) {
            return back()->withInput()->withErrors(['slug' => $exception->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.created')]);

        return redirect()->route('admin.forms.index');
    }

    public function edit(Form $form): View
    {
        return view('baobab::admin.forms.edit', [
            'form' => $form,
            'fieldTypes' => [...FormFieldTypes::allowed(), 'consent'],
            'typesNeedingChoices' => FormFieldsNormalizer::TYPES_NEEDING_CHOICES,
            'previewFields' => $this->previewFields($form),
        ]);
    }

    public function update(Form $form, Request $request, SaveForm $action): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['nullable', 'string'],
        ]);

        /** @var mixed $decodedFields */
        $decodedFields = json_decode($validated['fields'] ?? '[]', true);
        $fields = FormFieldsNormalizer::normalize($decodedFields);

        try {
            $action($form, ['title' => $validated['title'], 'slug' => $form->slug, 'fields' => $fields]);
        } catch (InvalidFormBlueprintException $exception) {
            return back()->withInput()->withErrors(['fields' => $exception->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.updated')]);

        return redirect()->route('admin.forms.edit', $form);
    }

    public function destroy(Form $form, DeleteForm $action): RedirectResponse
    {
        $action($form);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.deleted')]);

        return redirect()->route('admin.forms.index');
    }

    /**
     * Champs prêts pour l'aperçu (admin.forms.edit) — même règle que
     * `ContentController::formFieldsForView()` : tout le calcul ici, la vue
     * ne fait que lire.
     *
     * @return list<array<string, mixed>>
     */
    private function previewFields(Form $form): array
    {
        return array_map(function (array $field): array {
            /** @var list<string> $choices */
            $choices = (array) ($field['options']['choices'] ?? []);

            return [
                ...$field,
                'choice_options' => array_combine($choices, $choices),
            ];
        }, (array) ($form->blueprint['fields'] ?? []));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'title', 'label' => __('baobab::admin.forms.column_title')],
            ['key' => 'slug', 'label' => __('baobab::admin.forms.column_slug')],
            ['key' => 'version', 'label' => __('baobab::admin.forms.column_version')],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (Form $form) => view('baobab::admin.forms.partials.row-actions', ['form' => $form])->render(),
            ],
        ];
    }
}
