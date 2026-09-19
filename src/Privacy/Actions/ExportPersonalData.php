<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Contracts\ExportsPersonalData;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\PersonalDataArchive;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Droit d'accès et portabilité (spec 16 §4.1). Interroge tous les
 * fournisseurs (`locate` puis `export`) et assemble une archive ZIP chiffrée
 * AES-256 : un dossier par fournisseur (`{key}/data.json` + `{key}/files/*`),
 * un `index.html` lisible à la racine.
 *
 * Renvoie l'archive et son mot de passe ; ne les remet à personne — la remise
 * par canaux distincts appartient à la Pass D (suivi n° 335). L'export est
 * audité (sans le contenu exporté, qui ne doit pas se recopier dans l'audit).
 */
final class ExportPersonalData
{
    public function __construct(
        private readonly PrivacyRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws NoPersonalDataException quand aucun fournisseur ne détient de donnée du sujet
     */
    public function __invoke(Subject $subject): PersonalDataArchive
    {
        $sections = [];
        $entries = [];
        $unsupported = [];

        foreach ($this->registry->all() as $key => $provider) {
            if (! $provider->locate($subject)) {
                continue;
            }

            if (! $provider instanceof ExportsPersonalData) {
                $unsupported[] = $key;

                continue;
            }

            $export = $provider->export($subject);
            $files = [];

            $entries["{$key}/data.json"] = (string) json_encode($export->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            foreach ($export->files as $name => $source) {
                $name = basename($name);

                if (! Storage::disk($source['disk'])->exists($source['path'])) {
                    continue;
                }

                $entries["{$key}/files/{$name}"] = (string) Storage::disk($source['disk'])->get($source['path']);
                $files[] = $name;
            }

            $sections[] = [
                'key' => $key,
                'title' => $provider->describe()->title,
                'rows' => $this->flatten($export->data),
                'files' => $files,
            ];
        }

        if ($sections === []) {
            throw NoPersonalDataException::forSubject();
        }

        $entries['index.html'] = view('baobab::privacy.export-index', [
            'sections' => $sections,
            'unsupported' => $unsupported,
            'generatedAt' => now(),
        ])->render();

        $password = Str::password(24, symbols: false);
        $path = $this->writeArchive($entries, $password);

        $this->audit->record('privacy.exported', $this->userOf($subject), [
            'providers' => array_column($sections, 'key'),
            'unsupported' => $unsupported,
        ]);

        return new PersonalDataArchive($path, $password, array_column($sections, 'key'), $unsupported);
    }

    /**
     * Aplati les données imbriquées en lignes `libellé => valeur` pour l'index
     * HTML : la vue ne fait qu'afficher (aucune logique de parcours en Blade).
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{label: string, value: string}>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix} › {$key}";

            if (is_array($value)) {
                array_push($rows, ...$this->flatten($value, $label));

                continue;
            }

            $rows[] = ['label' => $label, 'value' => match (true) {
                $value === null => '—',
                is_bool($value) => $value ? 'oui' : 'non',
                default => is_scalar($value) ? (string) $value : '',
            }];
        }

        return $rows;
    }

    private function userOf(Subject $subject): ?User
    {
        return $subject->userId === null
            ? User::query()->whereRaw('LOWER(email) = ?', [$subject->email])->first()
            : User::query()->find($subject->userId);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeArchive(array $entries, string $password): string
    {
        $path = tempnam(sys_get_temp_dir(), 'baobab_privacy_');

        if ($path === false) {
            throw new RuntimeException("Impossible de créer un fichier temporaire pour l'archive d'export.");
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive d'export : {$path}.");
        }

        $zip->setPassword($password);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
        }

        $zip->close();

        return $path;
    }
}
