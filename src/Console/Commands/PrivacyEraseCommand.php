<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Console\Commands\Concerns\ResolvesPrivacySubject;
use Baobab\Privacy\Actions\ErasePersonalData;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:privacy:erase {subject}` (spec 16 §7) — équivalent
 * console de `ErasePersonalData`. Irréversible : confirmation explicite, sauf
 * `--force` pour l'automatisation. Exécution immédiate tant que le délai de
 * grâce n'existe pas (décision 9).
 */
final class PrivacyEraseCommand extends Command
{
    use ResolvesPrivacySubject;

    protected $signature = 'baobab:privacy:erase
        {subject : Identifiant de compte ou adresse e-mail}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Efface les données personnelles d\'un sujet (droit à l\'oubli).';

    public function handle(ErasePersonalData $erase): int
    {
        $subject = $this->resolveSubject((string) $this->argument('subject'));

        if ($subject === null) {
            $this->error(__('baobab::privacy.export.unknown_subject'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(__('baobab::privacy.erasure.confirm'), false)) {
            $this->warn(__('baobab::privacy.erasure.aborted'));

            return self::FAILURE;
        }

        try {
            $result = $erase($subject);
        } catch (NoPersonalDataException|AdminLockoutException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(__('baobab::privacy.erasure.done', ['reference' => $result->reference]));

        foreach ($result->reports as $key => $report) {
            $this->line("  {$key} — {$report->outcome->value} ({$report->count}) : {$report->note}");
        }

        if ($result->unsupported !== []) {
            $this->warn(__('baobab::privacy.erasure.unsupported', ['providers' => implode(', ', $result->unsupported)]));
        }

        return self::SUCCESS;
    }
}
