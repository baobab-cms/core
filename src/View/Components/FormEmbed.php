<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Forms\Models\Form;
use Baobab\Forms\Support\FormFieldPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::form-embed slug="contact" />` (spec 14 §4, M8 point 6 Pass C1) —
 * rendu public par défaut d'un formulaire, sur les tokens du design system
 * (principe 8, cascade `ResolveDesignTokens`). Nommé `form-embed` plutôt que
 * `form` à la lettre de la spec : collision avec `Baobab\View\Components\Form`
 * (wrapper CRUD admin, déjà partout en admin) relevée en ouvrant cette passe,
 * tranchée avec l'utilisateur — suivi n° 266.
 *
 * Résolution par slug, jamais par identifiant : c'est ce qu'un thème connaît.
 * Un slug inconnu ne fait échouer ni la page ni le composant — patron
 * `EntryLink`/`Auto` (« un rôle sans matière ne rend rien du tout ») —
 * `shouldRender()` retourne `false`, aucun conteneur vide, aucune erreur.
 *
 * La confirmation (`baobab_form_confirmation`, flashée par
 * `SubmitFormController`) est scopée par slug pour ne s'afficher que sous le
 * formulaire réellement soumis, si plusieurs vivent sur la même page.
 */
final class FormEmbed extends Component
{
    public ?Form $form = null;

    /** @var list<array<string, mixed>> */
    public array $fields = [];

    public ?string $confirmationMessage = null;

    public function __construct(public string $slug)
    {
        $this->form = Form::query()->where('slug', $slug)->first();

        if ($this->form === null) {
            return;
        }

        $this->fields = FormFieldPresenter::present((array) ($this->form->blueprint['fields'] ?? []));

        /** @var array{slug?: string, message?: string}|null $confirmation */
        $confirmation = session('baobab_form_confirmation');

        if ($confirmation !== null && ($confirmation['slug'] ?? null) === $slug) {
            $this->confirmationMessage = $confirmation['message'] ?? null;
        }
    }

    public function shouldRender(): bool
    {
        return $this->form !== null;
    }

    public function render(): View
    {
        return view('baobab::components.form-embed');
    }
}
