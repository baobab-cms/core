<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Forms\Models\Form;
use Baobab\Forms\Support\FormFieldPresenter;
use Baobab\Forms\Support\FormSpamGuard;
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
 *
 * `mode="fragment"` (Pass C2) demande une soumission sans rechargement : le
 * même `<form>`, posté au même endpoint, mais intercepté par Alpine plutôt
 * que laissé au navigateur — dégradation gracieuse assurée par construction,
 * pas par détection : sans JavaScript, l'écouteur ne s'attache jamais et le
 * navigateur poste nativement, exactement comme en mode `redirect`. Une
 * valeur inconnue retombe sur `redirect` plutôt que de faire confiance à une
 * valeur arbitraire (patron `FormSettingsNormalizer::CAPTCHA_PROVIDERS`).
 *
 * `hasFileField` (Pass C3) pilote l'`enctype` du `<form>` posé par la vue —
 * calculé une fois ici plutôt que dans la vue, qui ne fait qu'afficher.
 *
 * `renderToken` (Pass D1) horodate ce rendu précis pour le piège temporel de
 * `FormSpamGuard` : recalculé à chaque exécution du constructeur, donc à
 * chaque swap de fragment (Pass C2) — le minuteur anti-spam repart bien de
 * zéro à chaque nouveau rendu, jamais du rendu initial de la page.
 */
final class FormEmbed extends Component
{
    /** @var list<string> */
    public const MODES = ['redirect', 'fragment'];

    public ?Form $form = null;

    /** @var list<array<string, mixed>> */
    public array $fields = [];

    public bool $hasFileField = false;

    public ?string $confirmationMessage = null;

    public string $mode;

    public string $renderToken = '';

    public function __construct(public string $slug, string $mode = 'redirect')
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : 'redirect';

        $this->form = Form::query()->where('slug', $slug)->first();

        if ($this->form === null) {
            return;
        }

        $this->fields = FormFieldPresenter::present((array) ($this->form->blueprint['fields'] ?? []));
        $this->hasFileField = collect($this->fields)->contains(fn (array $field): bool => $field['type'] === 'file');
        $this->renderToken = FormSpamGuard::renderToken();

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
