<?php

use Baobab\Mail\Jobs\SendQueuedMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

it('queues a test e-mail and confirms in the console output', function () {
    Queue::fake();

    $exitCode = Artisan::call('baobab:mail:test', ['address' => 'dest@example.com']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('dest@example.com');

    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'dest@example.com');
});
