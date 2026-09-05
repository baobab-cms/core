<?php

declare(strict_types=1);

namespace Baobab\Forms\Http\Controllers;

use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * `POST /baobab/forms/{form:slug}` (spec 14 §4, M8 point 6 Pass C1) —
 * adaptateur mince sur `SubmitForm` (Pass A), qui porte déjà toute la
 * validation, y compris le hook `baobab.form.validating`. Une
 * `ValidationException` non interceptée ici redirige automatiquement vers la
 * page précédente avec erreurs et saisie flashées (comportement Laravel
 * natif, `Illuminate\Foundation\Exceptions\Handler`) — le « fonctionne sans
 * JavaScript » de la spec, sans code dédié.
 *
 * `_form_slug` (champ caché posé par `<x-baobab::form-embed>`) n'entre jamais
 * dans le payload validé : il ne sert qu'à `FormEmbed`, au retour, pour
 * distinguer laquelle des soumissions flashées le concerne quand plusieurs
 * formulaires vivent sur la même page.
 */
final class SubmitFormController extends Controller
{
    public function __invoke(Request $request, Form $form, SubmitForm $action): RedirectResponse
    {
        $input = $request->except(['_token', '_form_slug']);

        $action($form, $input, $request->ip());

        $message = (string) ($form->settings['confirmation']['message'] ?? '');

        return back()->with('baobab_form_confirmation', [
            'slug' => $form->slug,
            'message' => $message !== '' ? $message : __('baobab::rendering.form_confirmation_default'),
        ]);
    }
}
