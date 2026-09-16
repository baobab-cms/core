<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Scheduler\SchedulerRegistrar;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * « Exécuter maintenant » (spec 12 §2.3) — les commandes du Core ne sont
 * enregistrées auprès d'Artisan que `runningInConsole()`
 * (BaobabServiceProvider::boot()) : `Artisan::call()` échoue donc
 * systématiquement (« command does not exist ») quand cette action part
 * d'une requête HTTP admin, faute de commande enregistrée dans CE process —
 * défaut constaté en recette navigateur avant même le premier merge de cette
 * passe. La commande (toujours une FQCN côté Core, `commandFor()`) est donc
 * résolue et exécutée directement via le conteneur, sans passer par le
 * registre d'Artisan. Une tâche de module déclarée par simple signature
 * (spec 12 §2.2, forme rare, aucun module du repo n'en déclare une à ce
 * jour) retombe sur `Artisan::call()`, seul capable de la résoudre — elle
 * suppose que son fournisseur l'enregistre hors contexte console, limite
 * pré-existante de cette forme, pas introduite ici. Journalisé dans
 * `scheduled_task_runs` via le même SchedulerRegistrar que les runs
 * automatiques (mêmes hooks `baobab.schedule.task.*`, spec §2.4).
 */
final class RunScheduledTask
{
    public function __construct(
        private readonly Application $app,
        private readonly SchedulerRegistrar $registrar,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(string $taskKey): bool
    {
        $command = $this->registrar->commandFor($taskKey);

        if ($command === null) {
            throw new InvalidArgumentException("Tâche planifiée inconnue : {$taskKey}");
        }

        $this->registrar->recordStart($taskKey);

        [$exitCode, $error] = $this->execute($command);

        $success = $exitCode === 0;

        $this->registrar->recordFinish($taskKey, $success ? 'success' : 'failed', $success ? null : $error);

        $this->audit->record('scheduler.task.run_manually', null, ['task_key' => $taskKey, 'success' => $success]);

        return $success;
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function execute(string $command): array
    {
        if (class_exists($command) && is_subclass_of($command, ConsoleCommand::class)) {
            return $this->executeCommandClass($command);
        }

        try {
            $exitCode = Artisan::call($command);

            return [$exitCode, $exitCode === 0 ? null : Str::limit(Artisan::output(), 1000)];
        } catch (Throwable $exception) {
            return [1, $exception->getMessage()];
        }
    }

    /**
     * @param  class-string<ConsoleCommand>  $commandClass
     * @return array{0: int, 1: ?string}
     */
    private function executeCommandClass(string $commandClass): array
    {
        $command = $this->app->make($commandClass);
        $command->setLaravel($this->app);

        $output = new BufferedOutput;

        try {
            $exitCode = $command->run(new ArrayInput([]), $output);
        } catch (Throwable $exception) {
            return [1, $exception->getMessage()];
        }

        return [$exitCode, $exitCode === 0 ? null : Str::limit($output->fetch(), 1000)];
    }
}
