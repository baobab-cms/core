<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Privacy\Actions\ExportPersonalData;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * `php artisan baobab:privacy:export {subject}` (spec 16 §7) — équivalent
 * console de `ExportPersonalData`. Le sujet est un identifiant de compte ou
 * une adresse e-mail. Le mot de passe de l'archive n'est affiché qu'ici, une
 * seule fois : rien ne le conserve.
 */
final class PrivacyExportCommand extends Command
{
    protected $signature = 'baobab:privacy:export
        {subject : Identifiant de compte ou adresse e-mail}
        {--output= : Dossier de destination (défaut : storage/app/privacy-exports)}';

    protected $description = 'Exporte les données personnelles d\'un sujet en archive ZIP chiffrée.';

    public function handle(ExportPersonalData $export): int
    {
        $subject = $this->resolveSubject((string) $this->argument('subject'));

        if ($subject === null) {
            $this->error(__('baobab::privacy.export.unknown_subject'));

            return self::FAILURE;
        }

        try {
            $archive = $export($subject);
        } catch (NoPersonalDataException $e) {
            $this->warn($e->getMessage());

            return self::FAILURE;
        }

        $directory = (string) ($this->option('output') ?: storage_path('app/privacy-exports'));
        File::ensureDirectoryExists($directory);
        $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'privacy-export-'.now()->format('Ymd-His').'.zip';
        File::move($archive->path, $target);

        $this->info(__('baobab::privacy.export.written', ['path' => $target]));
        $this->line(__('baobab::privacy.export.providers', ['providers' => implode(', ', $archive->providers)]));

        if ($archive->unsupported !== []) {
            $this->warn(__('baobab::privacy.export.unsupported_title').' : '.implode(', ', $archive->unsupported));
        }

        $this->line(__('baobab::privacy.export.password', ['password' => $archive->password]));
        $this->line(__('baobab::privacy.export.password_once'));

        return self::SUCCESS;
    }

    private function resolveSubject(string $raw): ?Subject
    {
        if (str_contains($raw, '@')) {
            return Subject::forEmail($raw);
        }

        $user = ctype_digit($raw) ? User::query()->find((int) $raw) : null;

        return $user === null ? null : Subject::forUser($user);
    }
}
