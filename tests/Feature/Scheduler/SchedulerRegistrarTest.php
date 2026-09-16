<?php

use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
use Baobab\Scheduler\Models\ScheduledTaskSuspension;
use Baobab\Scheduler\ScheduledTaskRunStatus;
use Baobab\Scheduler\SchedulerRegistrar;
use Illuminate\Console\Scheduling\Schedule;

it('registers the two Core content tasks every minute', function () {
    $schedule = app(Schedule::class);

    app(SchedulerRegistrar::class)->register($schedule);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');

    expect($commands)->toContain('content:publish-due')
        ->toContain('content:unpublish-due');
});

it('registers the notifications purge command daily', function () {
    $schedule = app(Schedule::class);

    app(SchedulerRegistrar::class)->register($schedule);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');

    expect($commands)->toContain('notifications:purge-old');
});

it('registers a task declared by an active module', function () {
    Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-newsletter',
        'status' => 'active',
        'manifest' => [
            'schedule' => [
                ['key' => 'acme.newsletter.digest', 'command' => 'acme:newsletter:digest', 'cron' => '0 8 * * *'],
            ],
        ],
    ]);

    $schedule = app(Schedule::class);
    app(SchedulerRegistrar::class)->register($schedule);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');

    expect($commands)->toContain('acme:newsletter:digest');
});

it('does not register an inactive module\'s scheduled task', function () {
    Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-newsletter',
        'status' => 'inactive',
        'manifest' => [
            'schedule' => [
                ['key' => 'acme.newsletter.digest', 'command' => 'acme:newsletter:digest', 'cron' => '0 8 * * *'],
            ],
        ],
    ]);

    $schedule = app(Schedule::class);
    app(SchedulerRegistrar::class)->register($schedule);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');

    expect($commands)->not->toContain('acme:newsletter:digest');
});

it('records a running then a successful execution history row', function () {
    $registrar = app(SchedulerRegistrar::class);

    $registrar->recordStart('baobab.content.publish-due');
    $running = ScheduledTaskRun::where('task_key', 'baobab.content.publish-due')->sole();
    expect($running->status)->toBe(ScheduledTaskRunStatus::Running)
        ->and($running->finished_at)->toBeNull();

    $registrar->recordFinish('baobab.content.publish-due', 'success');
    $finished = $running->fresh();

    expect($finished->status)->toBe(ScheduledTaskRunStatus::Success)
        ->and($finished->finished_at)->not->toBeNull();
});

it('records a failed execution with its error message', function () {
    $registrar = app(SchedulerRegistrar::class);

    $registrar->recordStart('baobab.content.unpublish-due');
    $registrar->recordFinish('baobab.content.unpublish-due', 'failed', 'Code de sortie : 1');

    $run = ScheduledTaskRun::where('task_key', 'baobab.content.unpublish-due')->sole();

    expect($run->status)->toBe(ScheduledTaskRunStatus::Failed)
        ->and($run->error)->toBe('Code de sortie : 1');
});

it('describes every core and module task, including a human-readable cron and next run', function () {
    Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-newsletter',
        'status' => 'active',
        'manifest' => [
            'schedule' => [
                ['key' => 'acme.newsletter.digest', 'command' => 'acme:newsletter:digest', 'cron' => '0 8 * * *', 'description' => 'Envoie le digest.'],
            ],
        ],
    ]);

    $tasks = collect(app(SchedulerRegistrar::class)->describeTasks());

    $core = $tasks->firstWhere('taskKey', 'baobab.content.publish-due');
    expect($core->source)->toBe('core')
        ->and($core->moduleName)->toBeNull()
        ->and($core->cronHuman)->toBe('Chaque minute')
        ->and($core->suspended)->toBeFalse()
        ->and($core->nextRun)->not->toBeNull();

    $moduleTask = $tasks->firstWhere('taskKey', 'acme.newsletter.digest');
    expect($moduleTask->source)->toBe('module')
        ->and($moduleTask->moduleName)->toBe('acme/newsletter')
        ->and($moduleTask->description)->toBe('Envoie le digest.')
        ->and($moduleTask->cronHuman)->toBe('Tous les jours à 08:00');
});

it('does not schedule a suspended module task but still lists it', function () {
    Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-newsletter',
        'status' => 'active',
        'manifest' => [
            'schedule' => [
                ['key' => 'acme.newsletter.digest', 'command' => 'acme:newsletter:digest', 'cron' => '0 8 * * *'],
            ],
        ],
    ]);

    ScheduledTaskSuspension::create(['task_key' => 'acme.newsletter.digest']);

    $schedule = app(Schedule::class);
    app(SchedulerRegistrar::class)->register($schedule);

    $commands = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');
    expect($commands)->not->toContain('acme:newsletter:digest');

    $described = collect(app(SchedulerRegistrar::class)->describeTasks())->firstWhere('taskKey', 'acme.newsletter.digest');
    expect($described->suspended)->toBeTrue()
        ->and($described->nextRun)->toBeNull();
});

it('refuses to report a core task key as belonging to a module', function () {
    $registrar = app(SchedulerRegistrar::class);

    expect($registrar->isCoreTask('baobab.content.publish-due'))->toBeTrue()
        ->and($registrar->isCoreTask('acme.newsletter.digest'))->toBeFalse();
});
