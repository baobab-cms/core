<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
use Baobab\System\Actions\RunScheduledTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Suivi n° 363, brique 2 — les branches d'exécution manuelle (spec 12 §2.3)
 * que `SchedulerControllerTest` n'atteint qu'indirectement (un seul chemin :
 * commande de classe Core qui réussit). `RunScheduledTask` a trois formes de
 * commande (classe qui réussit, classe qui échoue sans lever, classe qui
 * lève) et deux pour la forme signature (Artisan::call réussit ou échoue,
 * avec ou sans exception) — chacune loggée dans scheduled_task_runs et
 * auditée quel que soit le résultat.
 */
function moduleWithScheduledTaskCommand(string $key, string $command): Module
{
    static $counter = 0;
    $counter++;

    return Module::create([
        'name' => "acme/run-scheduled-task-fixture-{$counter}",
        'title' => 'Run Scheduled Task Fixture',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\RunScheduledTaskFixture\\Providers\\ServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-run-scheduled-task-fixture',
        'status' => 'active',
        'manifest' => [
            'schedule' => [
                ['key' => $key, 'command' => $command, 'cron' => '0 0 * * *', 'description' => 'Fixture de test.'],
            ],
        ],
    ]);
}

final class RunScheduledTaskSucceedingCommand extends Command
{
    protected $signature = 'test:run-scheduled-task-class-succeeds';

    protected $description = 'Double de test — classe qui réussit.';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

final class RunScheduledTaskFailingCommand extends Command
{
    protected $signature = 'test:run-scheduled-task-class-fails';

    protected $description = 'Double de test — classe qui échoue sans lever.';

    public function handle(): int
    {
        $this->error('échec attendu, sans exception');

        return self::FAILURE;
    }
}

final class RunScheduledTaskThrowingCommand extends Command
{
    protected $signature = 'test:run-scheduled-task-class-throws';

    protected $description = 'Double de test — classe qui lève.';

    public function handle(): int
    {
        throw new RuntimeException('kaboom');
    }
}

beforeEach(function () {
    Artisan::command('test:run-scheduled-task-string-succeeds', function (): int {
        return 0;
    });

    Artisan::command('test:run-scheduled-task-string-fails', function (): int {
        $this->error('échec attendu, sans exception, forme signature');

        return 1;
    });
});

it('refuses an unknown task key, without recording a run or an audit entry', function () {
    expect(fn () => app(RunScheduledTask::class)('inconnue.tache'))
        ->toThrow(InvalidArgumentException::class, 'Tâche planifiée inconnue : inconnue.tache');

    expect(ScheduledTaskRun::count())->toBe(0)
        ->and(AuditEntry::where('action', 'scheduler.task.run_manually')->count())->toBe(0);
});

it('runs a class-based command that succeeds', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.class-succeeds', RunScheduledTaskSucceedingCommand::class);

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.class-succeeds');

    expect($success)->toBeTrue();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.class-succeeds')->sole();
    expect($run->status->value)->toBe('success')
        ->and($run->error)->toBeNull()
        ->and($run->finished_at)->not->toBeNull();

    $entry = AuditEntry::where('action', 'scheduler.task.run_manually')->sole();
    expect($entry->data)->toBe(['task_key' => 'acme.run-scheduled-task.class-succeeds', 'success' => true]);
});

it('runs a class-based command that fails without throwing, capturing its output as the error', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.class-fails', RunScheduledTaskFailingCommand::class);

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.class-fails');

    expect($success)->toBeFalse();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.class-fails')->sole();
    expect($run->status->value)->toBe('failed')
        ->and($run->error)->toContain('échec attendu, sans exception');
});

it('runs a class-based command that throws, capturing the exception message as the error', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.class-throws', RunScheduledTaskThrowingCommand::class);

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.class-throws');

    expect($success)->toBeFalse();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.class-throws')->sole();
    expect($run->status->value)->toBe('failed')
        ->and($run->error)->toBe('kaboom');
});

it('runs a plain signature command via Artisan::call() when it succeeds', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.string-succeeds', 'test:run-scheduled-task-string-succeeds');

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.string-succeeds');

    expect($success)->toBeTrue();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.string-succeeds')->sole();
    expect($run->status->value)->toBe('success')
        ->and($run->error)->toBeNull();
});

it('runs a plain signature command via Artisan::call() when it fails without throwing', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.string-fails', 'test:run-scheduled-task-string-fails');

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.string-fails');

    expect($success)->toBeFalse();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.string-fails')->sole();
    expect($run->status->value)->toBe('failed')
        ->and($run->error)->toContain('échec attendu, sans exception, forme signature');
});

it('catches Artisan::call() throwing on an unregistered signature, as failed rather than crashing', function () {
    moduleWithScheduledTaskCommand('acme.run-scheduled-task.string-throws', 'test:run-scheduled-task-string-unregistered');

    $success = app(RunScheduledTask::class)('acme.run-scheduled-task.string-throws');

    expect($success)->toBeFalse();

    $run = ScheduledTaskRun::where('task_key', 'acme.run-scheduled-task.string-throws')->sole();
    expect($run->status->value)->toBe('failed')
        ->and($run->error)->not->toBeNull();
});
