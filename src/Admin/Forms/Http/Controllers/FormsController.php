<?php

declare(strict_types=1);

namespace Baobab\Admin\Forms\Http\Controllers;

use Baobab\Forms\Actions\DeleteForm;
use Baobab\Forms\Actions\ExportForm;
use Baobab\Forms\Actions\ImportForm;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Exceptions\DuplicateFormSlugException;
use Baobab\Forms\Exceptions\InvalidFormBlueprintException;
use Baobab\Forms\Exceptions\InvalidFormExportException;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Support\FormFieldsNormalizer;
use Baobab\Forms\Support\FormFieldTypes;
use Baobab\Forms\Support\FormSettingsNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

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
            'settingsForm' => $this->settingsForm($form),
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

    /**
     * Suites (spec 14 §8), anti-spam (§7) et rétention (§6.4) — trois sections
     * du même écran (§3), soumises indépendamment du formulaire de champs.
     * Réutilise `SaveForm` (Pass A) comme les champs : mêmes garanties
     * (version incrémentée, audit, hook), aucune Action dédiée n'a de raison
     * d'exister pour un sous-ensemble des mêmes colonnes. Les champs
     * courants sont repassés tels quels — cette action ne les touche jamais.
     */
    public function updateSettings(Form $form, Request $request, SaveForm $action): RedirectResponse
    {
        $validated = $request->validate([
            'store_submissions' => ['nullable', 'boolean'],
            'retention_days' => ['required', 'integer', 'min:1'],
            'retain_ip' => ['nullable', 'boolean'],
            'suites' => ['nullable', 'array'],
            'suites.email_notification.enabled' => ['nullable', 'boolean'],
            'suites.email_notification.recipients' => ['nullable', 'string'],
            'suites.acknowledgement.enabled' => ['nullable', 'boolean'],
            'suites.admin_notification.enabled' => ['nullable', 'boolean'],
            'suites.webhook.enabled' => ['nullable', 'boolean'],
            'captcha_provider' => ['nullable', 'string', 'in:'.implode(',', FormSettingsNormalizer::CAPTCHA_PROVIDERS)],
            'captcha_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $action($form, [
            'title' => $form->title,
            'slug' => $form->slug,
            'fields' => (array) ($form->blueprint['fields'] ?? []),
            'settings' => FormSettingsNormalizer::normalize($validated),
            'store_submissions' => (bool) ($validated['store_submissions'] ?? false),
            'retention_days' => $validated['retention_days'],
            'retain_ip' => (bool) ($validated['retain_ip'] ?? false),
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.settings_updated')]);

        return redirect()->route('admin.forms.edit', $form);
    }

    public function export(Form $form, ExportForm $action): Response
    {
        return response($action($form))
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"{$form->slug}.json\"");
    }

    public function import(Request $request, ImportForm $action): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $form = $action((string) file_get_contents((string) $file->getRealPath()));
        } catch (InvalidFormExportException|InvalidFormBlueprintException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.forms.imported')]);

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
            // `consent` affiche son texte légal en priorité (§2.2) ; tous les
            // autres retombent sur le libellé saisi puis sur la clé.
            $label = (string) ($field['type'] === 'consent' ? ($field['options']['text'] ?? $field['label'] ?? $field['key']) : ($field['label'] ?? $field['key']));

            return [
                ...$field,
                'choice_options' => array_combine($choices, $choices),
                // Convention de formulaire : un astérisque à côté du libellé
                // signale un champ requis — absent tant que ce n'est pas
                // calculé ici (les composants `field.*` ne le déduisent pas
                // de `required`, qu'ils ne reçoivent même pas).
                'display_label' => ($field['required'] ?? false) ? "{$label} *" : $label,
            ];
        }, (array) ($form->blueprint['fields'] ?? []));
    }

    /**
     * Réglages tels que la vue les affiche — jamais un accès direct à
     * `$form->settings` depuis le Blade, dont la forme (sac JSON, vide sur
     * un formulaire jamais passé par cette passe) ne doit être connue qu'ici.
     *
     * @return array<string, mixed>
     */
    private function settingsForm(Form $form): array
    {
        $settings = $form->settings;

        return [
            'store_submissions' => $form->store_submissions,
            'retention_days' => $form->retention_days,
            'retain_ip' => $form->retain_ip,
            'email_notification_enabled' => (bool) ($settings['suites']['email_notification']['enabled'] ?? false),
            'email_notification_recipients' => implode("\n", (array) ($settings['suites']['email_notification']['recipients'] ?? [])),
            'acknowledgement_enabled' => (bool) ($settings['suites']['acknowledgement']['enabled'] ?? false),
            'admin_notification_enabled' => (bool) ($settings['suites']['admin_notification']['enabled'] ?? false),
            'webhook_enabled' => (bool) ($settings['suites']['webhook']['enabled'] ?? false),
            'captcha_provider' => (string) ($settings['anti_spam']['captcha']['provider'] ?? 'none'),
            'captcha_site_key' => (string) ($settings['anti_spam']['captcha']['site_key'] ?? ''),
            'captcha_secret_key' => (string) ($settings['anti_spam']['captcha']['secret_key'] ?? ''),
        ];
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
