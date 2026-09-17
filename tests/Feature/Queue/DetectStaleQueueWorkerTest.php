<?php

use Baobab\Queue\Actions\DetectStaleQueueWorker;
use Illuminate\Support\Facades\DB;

it('returns null when the queue is empty', function () {
    expect(app(DetectStaleQueueWorker::class)())->toBeNull();
});

it('returns null when the oldest pending job is fresh', function () {
    config(['baobab.queues.stale_worker_minutes' => 5]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    expect(app(DetectStaleQueueWorker::class)())->toBeNull();
});

it('returns the elapsed minutes once the oldest pending job crosses the threshold', function () {
    config(['baobab.queues.stale_worker_minutes' => 5]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->timestamp]);

    expect(app(DetectStaleQueueWorker::class)())->toBe(10);
});
