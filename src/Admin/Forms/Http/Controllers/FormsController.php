<?php

declare(strict_types=1);

namespace Baobab\Admin\Forms\Http\Controllers;

use Baobab\Forms\Actions\DeleteForm;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Exceptions\DuplicateFormSlugException;
use Baobab\Forms\Models\Form;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Formulaires » (spec 14 §3, M8 point 6, Pass B1) — le shell : liste,
 * création d'un formulaire vide (titre + slug), suppression. Adaptateur mince,
 * patron `WebhookSubscriptionsController` : aucune règle métier ici, tout est
 * dans `SaveForm`/`DeleteForm` (Pass A).
 *
 * L'édition des champs (palette, glisser-déposer, réglages) n'existe pas
 * encore — c'est la Pass B2. `store()` redirige donc vers la liste, pas vers
 * une fiche d'édition qui n'a rien à montrer.
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

    public function destroy(Form $form, DeleteForm $action): RedirectResponse
    {
        $action($form);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.deleted')]);

        return redirect()->route('admin.forms.index');
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
