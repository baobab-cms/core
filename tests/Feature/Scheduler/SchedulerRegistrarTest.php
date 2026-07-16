<?php

use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
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
    expect($running->status)->toBe('running')
        ->and($running->finished_at)->toBeNull();

    $registrar->recordFinish('baobab.content.publish-due', 'success');
    $finished = $running->fresh();

    expect($finished->status)->toBe('success')
        ->and($finished->finished_at)->not->toBeNull();
});

it('records a failed execution', function () {
    $registrar = app(SchedulerRegistrar::class);

    $registrar->recordStart('baobab.content.unpublish-due');
    $registrar->recordFinish('baobab.content.unpublish-due', 'failed');

    expect(ScheduledTaskRun::where('task_key', 'baobab.content.unpublish-due')->sole()->status)->toBe('failed');
});
