<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Forms\Exceptions\InvalidFormExportException;
use Baobab\Forms\Models\Form;

/**
 * Importe un formulaire depuis le JSON produit par `ExportForm` (spec 14
 * §2.1, §10). Un slug déjà connu **met à jour** le formulaire existant
 * plutôt que d'en refuser l'import — c'est précisément ce que le workflow
 * agence staging → prod (§10) attend d'un réimport après retouche, patron
 * `ModuleGenerator`/`InstallModule` : produire, réimporter, ne jamais forcer
 * une suppression manuelle entre les deux.
 *
 * Passe par `SaveForm` (Pass A) comme tout autre enregistrement : mêmes
 * garanties (validation du blueprint, version incrémentée, audit, hook).
 */
final class ImportForm
{
    public function __construct(private readonly SaveForm $save) {}

    public function __invoke(string $json): Form
    {
        /** @var mixed $data */
        $data = json_decode($json, associative: true);

        if (! is_array($data)) {
            throw InvalidFormExportException::malformedJson();
        }

        $source = (string) ($data['source'] ?? '');

        if ($source !== 'admin') {
            throw InvalidFormExportException::unsupportedSource($source);
        }

        $formatVersion = (int) ($data['format_version'] ?? 0);

        if ($formatVersion !== ExportForm::FORMAT_VERSION) {
            throw InvalidFormExportException::unsupportedFormatVersion($formatVersion);
        }

        $slug = (string) ($data['slug'] ?? '');

        if ($slug === '') {
            throw InvalidFormExportException::missingSlug();
        }

        $existing = Form::query()->where('slug', $slug)->first();

        return ($this->save)($existing, [
            'slug' => $slug,
            'title' => (string) ($data['title'] ?? ''),
            'fields' => (array) ($data['fields'] ?? []),
            'settings' => (array) ($data['settings'] ?? []),
            'store_submissions' => (bool) ($data['store_submissions'] ?? true),
            'retention_days' => (int) ($data['retention_days'] ?? 365),
            'retain_ip' => (bool) ($data['retain_ip'] ?? false),
        ]);
    }
}
