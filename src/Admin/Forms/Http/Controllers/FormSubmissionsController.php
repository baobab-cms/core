<?php

declare(strict_types=1);

namespace Baobab\Admin\Forms\Http\Controllers;

use Baobab\Audit\AuditLogger;
use Baobab\Forms\Actions\DeleteFormSubmission;
use Baobab\Forms\Actions\ExportFormSubmissionsCsv;
use Baobab\Forms\Actions\MarkFormSubmissionStatus;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation des soumissions (spec 14 §6.2, M8 point 6, Pass B5) — patron
 * `MailLogController` (filtres, pagination) et `RedirectsController::export()`
 * (export CSV). Sous-écran de `admin/forms`, comme `WebhookDeliveriesController`
 * l'est de `WebhookSubscriptionsController`.
 *
 * Accès gouverné par `baobab.system.forms.submissions_view` (routes/admin.php)
 * pour l'index/la fiche/le marquage — la spec 14 §9 ne nomme un permission
 * séparé que pour l'export et la suppression, jamais pour le marquage.
 * `downloadFile()` (Pass C3) réutilise ce même permission : une pièce jointe
 * est une donnée de soumission comme une autre, pas une surface à part.
 */
final class FormSubmissionsController
{
    public function index(Form $form, Request $request): View
    {
        $submissions = $this->filteredQuery($form, $request)
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('baobab::admin.forms.submissions.index', [
            'form' => $form,
            'submissions' => $submissions,
            'columns' => $this->columns($form),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function show(Form $form, FormSubmission $submission): View
    {
        return view('baobab::admin.forms.submissions.show', [
            'form' => $form,
            'submission' => $submission,
            'fields' => $this->snapshotFields($form, $submission),
        ]);
    }

    public function markStatus(Form $form, FormSubmission $submission, Request $request, MarkFormSubmissionStatus $action): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(FormSubmissionStatus::cases(), 'value'))],
        ]);

        $action($submission, FormSubmissionStatus::from($validated['status']));

        return redirect()->route('admin.forms.submissions.index', ['form' => $form->id]);
    }

    public function export(Form $form, Request $request, ExportFormSubmissionsCsv $action, AuditLogger $audit): Response
    {
        $query = $this->filteredQuery($form, $request)->orderByDesc('created_at');
        $count = (clone $query)->count();

        $csv = $action($form, $query);

        $audit->record('form_submissions.exported', $form, ['count' => $count]);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$form->slug}-submissions.csv\"");
    }

    public function destroy(Form $form, FormSubmission $submission, DeleteFormSubmission $action): RedirectResponse
    {
        $action($submission);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.form_submissions.deleted')]);

        return redirect()->route('admin.forms.submissions.index', ['form' => $form->id]);
    }

    /**
     * `signed` **et** permissionnée (spec 14 §5) — la route porte déjà
     * `can:baobab.system.forms.submissions_view` (routes/admin.php), ce
     * contrôleur ne revérifie donc que la présence réelle du fichier.
     * Premier téléchargement de fichier stocké proxié par le Core (Pass C3) :
     * aucun autre écran ne sert un fichier autrement qu'en le publiant sur le
     * disque public.
     */
    public function downloadFile(Form $form, FormSubmission $submission, string $field): StreamedResponse
    {
        /** @var array{stored_path?: string, original_name?: string}|null $reference */
        $reference = $submission->payload[$field] ?? null;

        abort_unless(is_array($reference) && isset($reference['stored_path']), 404);

        $disk = (string) config('baobab.forms.disk', 'local');

        abort_unless(Storage::disk($disk)->exists($reference['stored_path']), 404);

        return Storage::disk($disk)->download($reference['stored_path'], $reference['original_name'] ?? basename($reference['stored_path']));
    }

    /**
     * Part de `FormSubmission::query()` plutôt que de `$form->submissions()`
     * (patron `MailLogController::index()`) : chaîner `when()` sur une
     * relation `HasMany` la fait rester une `HasMany` à l'exécution, que
     * Larastan modélise en `Builder` — un vrai `Builder` dès le départ
     * n'a pas cet écart entre le type statique et le type réel.
     *
     * @return Builder<FormSubmission>
     */
    private function filteredQuery(Form $form, Request $request): Builder
    {
        return FormSubmission::query()
            ->where('form_id', $form->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')));
    }

    /**
     * Colonnes de la liste — dérivées du blueprint **courant** (patron de
     * `ExportFormSubmissionsCsv` : des en-têtes stables plutôt qu'un
     * blueprint différent par ligne). La fiche de détail (`show()`), elle,
     * lit le snapshot propre à chaque soumission.
     *
     * @return list<array<string, mixed>>
     */
    private function columns(Form $form): array
    {
        $fieldColumns = array_map(fn (array $field): array => [
            'key' => "payload.{$field['key']}",
            'label' => (string) ($field['label'] ?? $field['key']),
            // `<x-baobab::table>` échoue silencieusement (« Array ») sur une
            // valeur non scalaire sans ce rendu explicite — `checkboxes`
            // rend un tableau de choix, `checkbox`/`consent` un booléen,
            // `file` (Pass C3) un lien signé vers la pièce jointe.
            'raw' => $field['type'] === 'file',
            'render' => $field['type'] === 'file'
                ? fn (FormSubmission $submission) => $this->fileLink($form, $submission, $field['key'])
                : fn (FormSubmission $submission) => $this->displayValue($submission->payload[$field['key']] ?? null),
        ], (array) ($form->blueprint['fields'] ?? []));

        return [
            ...$fieldColumns,
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.form_submissions.column_date'),
                'render' => fn (FormSubmission $submission) => $submission->created_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'status',
                'label' => __('baobab::admin.form_submissions.column_status'),
                'raw' => true,
                'render' => fn (FormSubmission $submission) => view('baobab::admin.forms.submissions.partials.status-badge', ['submission' => $submission])->render(),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (FormSubmission $submission) => view('baobab::admin.forms.submissions.partials.row-actions', [
                    'form' => $form,
                    'submission' => $submission,
                    'statusOptions' => $this->statusOptions(),
                ])->render(),
            ],
        ];
    }

    /**
     * Lien signé (30 min) et permissionné vers `downloadFile()` (spec 14 §5).
     * Signature courte volontaire : une soumission consultée se télécharge
     * dans la foulée, pas des jours plus tard — contrairement au lien de
     * préview de thème (30 min également, même raisonnement).
     */
    private function fileLink(Form $form, FormSubmission $submission, string $fieldKey): string
    {
        /** @var array{original_name?: string, stored_path?: string}|null $reference */
        $reference = $submission->payload[$fieldKey] ?? null;

        if (! is_array($reference) || ! isset($reference['stored_path'])) {
            return __('baobab::admin.form_submissions.no_file');
        }

        $url = URL::temporarySignedRoute('admin.forms.submissions.files.show', now()->addMinutes(30), [
            'form' => $form->id,
            'submission' => $submission->id,
            'field' => $fieldKey,
        ]);

        $name = e($reference['original_name'] ?? basename($reference['stored_path']));

        return "<a href=\"{$url}\" class=\"underline\">{$name}</a>";
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(strval(...), $value));
        }

        if (is_bool($value)) {
            return $value ? __('baobab::admin.form_submissions.value_yes') : __('baobab::admin.form_submissions.value_no');
        }

        return (string) ($value ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $options = ['' => __('baobab::admin.form_submissions.filter_all')];

        foreach (FormSubmissionStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Champs de la fiche de détail, résolus depuis le snapshot de **cette**
     * soumission — jamais le blueprint courant, sauf pour une soumission
     * antérieure à la Pass B5 dont le snapshot n'a jamais été capturé.
     *
     * @return list<array<string, mixed>>
     */
    private function snapshotFields(Form $form, FormSubmission $submission): array
    {
        $snapshot = $submission->blueprint_snapshot;
        $fields = $snapshot !== null && $snapshot !== [] ? $snapshot : (array) ($form->blueprint['fields'] ?? []);

        return array_map(fn (array $field): array => [
            'key' => $field['key'],
            'label' => (string) ($field['label'] ?? $field['key']),
            'raw' => $field['type'] === 'file',
            'value' => $field['type'] === 'file'
                ? $this->fileLink($form, $submission, $field['key'])
                : $this->displayValue($submission->payload[$field['key']] ?? null),
        ], $fields);
    }
}
