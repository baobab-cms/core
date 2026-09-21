<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Console\Commands\Concerns\ResolvesPrivacySubject;
use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\ErasePersonalData;
use Baobab\Privacy\Exceptions\ErasureAlreadyScheduledException;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:privacy:erase {subject}` (spec 16 §7, décision 12) —
 * **planifie** par défaut une demande d'effacement sous délai de grâce,
 * comme l'écran admin ; annulable depuis `admin/privacy/requests`.
 * `--now` exécute `ErasePersonalData` immédiatement, sans délai : irréversible,
 * confirmation explicite sauf `--force` pour l'automatisation.
 */
final class PrivacyEraseCommand extends Command
{
    use ResolvesPrivacySubject;

    protected $signature = 'baobab:privacy:erase
        {subject : Identifiant de compte ou adresse e-mail}
        {--now : Exécuter immédiatement, sans délai de grâce}
        {--force : Avec --now, ne pas demander de confirmation}';

    protected $description = 'Planifie l\'effacement des données personnelles d\'un sujet (droit à l\'oubli), ou l\'exécute avec --now.';

    public function handle(CreatePrivacyRequest $create, ErasePersonalData $erase): int
    {
        $subject = $this->resolveSubject((string) $this->argument('subject'));

        if ($subject === null) {
            $this->error(__('baobab::privacy.export.unknown_subject'));

            return self::FAILURE;
        }

        if (! $this->option('now')) {
            return $this->schedule($create, $subject);
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

    private function schedule(CreatePrivacyRequest $create, Subject $subject): int
    {
        try {
            $request = $create($subject, null, 'cli', PrivacyRequestType::Erasure);
        } catch (AdminLockoutException|ErasureAlreadyScheduledException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(__('baobab::privacy.erasure.scheduled', [
            'date' => $request->scheduled_for?->format('Y-m-d H:i') ?? '',
            'uuid' => $request->uuid,
        ]));

        return self::SUCCESS;
    }
}
