<?php

use Baobab\System\HealthChecks\QueueWorkerCheck;
use Illuminate\Support\Facades\DB;

it('reports ok when the queue is empty', function () {
    $result = app(QueueWorkerCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});

it('warns once the oldest pending job crosses the configured threshold', function () {
    config(['baobab.queues.stale_worker_minutes' => 5]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->timestamp]);

    $result = app(QueueWorkerCheck::class)->run();

    expect($result->status->value)->toBe('warning');
});
