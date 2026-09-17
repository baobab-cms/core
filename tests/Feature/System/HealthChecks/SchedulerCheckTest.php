<?php

use Baobab\Scheduler\Models\ScheduledTaskRun;
use Baobab\System\HealthChecks\SchedulerCheck;

it('warns when no scheduled run has ever been recorded', function () {
    $result = app(SchedulerCheck::class)->run();

    expect($result->status->value)->toBe('warning');
});

it('reports ok when the most recent run started less than 2 minutes ago', function () {
    ScheduledTaskRun::create(['task_key' => 'baobab.content.publish-due', 'started_at' => now(), 'status' => 'success']);

    $result = app(SchedulerCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});

it('warns between 2 and 5 minutes of silence', function () {
    ScheduledTaskRun::create(['task_key' => 'baobab.content.publish-due', 'started_at' => now()->subMinutes(3), 'status' => 'success']);

    $result = app(SchedulerCheck::class)->run();

    expect($result->status->value)->toBe('warning');
});

it('fails past 5 minutes of silence', function () {
    ScheduledTaskRun::create(['task_key' => 'baobab.content.publish-due', 'started_at' => now()->subMinutes(6), 'status' => 'success']);

    $result = app(SchedulerCheck::class)->run();

    expect($result->status->value)->toBe('failed');
});
