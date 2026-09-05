<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Forms\Models\Form;

/**
 * Supprime un formulaire (spec 14 §3) — les soumissions partent avec lui
 * (`form_submissions.form_id` en cascade, migration `create_form_submissions_table`),
 * ce que la spec attend implicitement : un formulaire supprimé n'a plus de
 * réglage de rétention pour les couvrir.
 */
final class DeleteForm
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Form $form): void
    {
        $slug = $form->slug;

        $form->delete();

        $this->audit->record('form.deleted', $form, ['slug' => $slug]);
        Hook::action('baobab.form.deleted', $slug);
    }
}
