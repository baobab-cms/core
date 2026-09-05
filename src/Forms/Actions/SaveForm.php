<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Forms\Blueprint\FormBlueprint;
use Baobab\Forms\Exceptions\DuplicateFormSlugException;
use Baobab\Forms\Models\Form;

/**
 * Crée ou met à jour un formulaire (spec 14 §3). Le blueprint (`fields`) est
 * toujours revalidé — `$form === null` crée, sinon met à jour et incrémente
 * `version` **à chaque enregistrement**, y compris quand seuls le titre ou
 * les réglages changent (spec 14 §3, à la lettre : pas seulement quand les
 * champs diffèrent — c'est le numéro que chaque soumission mémorisera).
 */
final class SaveForm
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(?Form $form, array $data): Form
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = array_values((array) ($data['fields'] ?? []));
        $blueprint = FormBlueprint::fromArray($fields);

        $slug = (string) ($data['slug'] ?? $form?->slug);

        $duplicateQuery = Form::query()->where('slug', $slug);

        if ($form !== null) {
            $duplicateQuery->where('id', '!=', $form->id);
        }

        if ($duplicateQuery->exists()) {
            throw DuplicateFormSlugException::forSlug($slug);
        }

        // Défauts explicitement résolus par branche plutôt qu'enchaînés en
        // `?->` : au-delà d'un certain nombre de champs, une chaîne de
        // nullsafe sur la même variable devient plus dure à relire que deux
        // branches simples — et PHPStan la lit mal (n° d'erreur
        // `nullsafe.neverNull`, contradictoire d'un champ à l'autre).
        $currentTitle = null;
        $currentSettings = [];
        $currentStoreSubmissions = true;
        $currentRetentionDays = 365;
        $currentRetainIp = false;
        $currentVersion = 0;

        if ($form !== null) {
            $currentTitle = $form->title;
            $currentSettings = $form->settings;
            $currentStoreSubmissions = $form->store_submissions;
            $currentRetentionDays = $form->retention_days;
            $currentRetainIp = $form->retain_ip;
            $currentVersion = $form->version;
        }

        $attributes = [
            'slug' => $slug,
            'title' => (string) ($data['title'] ?? $currentTitle),
            'blueprint' => $blueprint->toArray(),
            'settings' => $data['settings'] ?? $currentSettings,
            'store_submissions' => (bool) ($data['store_submissions'] ?? $currentStoreSubmissions),
            'retention_days' => (int) ($data['retention_days'] ?? $currentRetentionDays),
            'retain_ip' => (bool) ($data['retain_ip'] ?? $currentRetainIp),
            'version' => $currentVersion + 1,
        ];

        if ($form === null) {
            $form = Form::create($attributes);
            $this->audit->record('form.created', $form);
            Hook::action('baobab.form.created', $form);

            return $form;
        }

        $form->update($attributes);
        $form = $form->fresh() ?? $form;
        $this->audit->record('form.updated', $form);
        Hook::action('baobab.form.updated', $form);

        return $form;
    }
}
