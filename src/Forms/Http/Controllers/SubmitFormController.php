<?php

declare(strict_types=1);

namespace Baobab\Forms\Http\Controllers;

use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;

/**
 * `POST /baobab/forms/{form:slug}` (spec 14 §4, M8 point 6 Pass C1/C2) —
 * adaptateur mince sur `SubmitForm` (Pass A), qui porte déjà toute la
 * validation, y compris le hook `baobab.form.validating`.
 *
 * **Même endpoint, même action, seule la négociation de la réponse change**
 * (spec §4, à la lettre) — le mode fragment (Pass C2) se distingue par un seul
 * en-tête, `X-Baobab-Form-Fragment`, posé par le JavaScript de
 * `<x-baobab::form-embed mode="fragment">` et absent de toute soumission
 * native. Sans cet en-tête (JavaScript absent ou `mode="redirect"`), une
 * `ValidationException` non interceptée redirige automatiquement vers la page
 * précédente avec erreurs et saisie flashées (comportement Laravel natif) —
 * le « fonctionne sans JavaScript » de la spec, sans code dédié.
 *
 * En mode fragment, `back()->withInput()->withErrors()` est appelé pour son
 * seul effet de bord (flasher saisie et erreurs en session, exactement ce que
 * ferait la redirection native) sans jamais renvoyer cette redirection : la
 * réponse est le même composant, re-rendu, retourné directement (422). Un
 * piège précis motive le `view()->share()` explicite juste après : `$errors`
 * a déjà été partagé aux vues par `ShareErrorsFromSession` **avant**
 * l'exécution de ce contrôleur — flasher une nouvelle valeur en session ne
 * met pas à jour cette référence déjà partagée, `old()` s'en sort seul
 * (lu depuis la session à l'appel, jamais pré-partagé) mais `$errors` non.
 *
 * `_form_slug` (champ caché posé par `<x-baobab::form-embed>`) n'entre jamais
 * dans le payload validé : il ne sert qu'à `FormEmbed`, au retour, pour
 * distinguer laquelle des soumissions flashées le concerne quand plusieurs
 * formulaires vivent sur la même page.
 */
final class SubmitFormController extends Controller
{
    public function __invoke(Request $request, Form $form, SubmitForm $action): RedirectResponse|Response
    {
        $wantsFragment = $request->header('X-Baobab-Form-Fragment') === '1';
        $input = $request->except(['_token', '_form_slug']);

        try {
            $action($form, $input, $request->ip());
        } catch (ValidationException $exception) {
            if (! $wantsFragment) {
                throw $exception;
            }

            back()->withInput()->withErrors($exception->validator);
            view()->share('errors', session('errors'));

            return response($this->renderEmbed($form), 422);
        }

        $message = (string) ($form->settings['confirmation']['message'] ?? '');

        session()->flash('baobab_form_confirmation', [
            'slug' => $form->slug,
            'message' => $message !== '' ? $message : __('baobab::rendering.form_confirmation_default'),
        ]);

        if ($wantsFragment) {
            return response($this->renderEmbed($form));
        }

        return back();
    }

    private function renderEmbed(Form $form): string
    {
        return Blade::render('<x-baobab::form-embed :slug="$slug" mode="fragment" />', ['slug' => $form->slug]);
    }
}
