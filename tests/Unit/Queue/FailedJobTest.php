<?php

use Baobab\Queue\Models\FailedJob;

it('extracts the display name from a well-formed payload', function () {
    $job = new FailedJob(['payload' => json_encode(['displayName' => 'App\\Jobs\\SendTestJob'])]);

    expect($job->jobName())->toBe('App\\Jobs\\SendTestJob');
});

it('falls back to the job key when displayName is absent', function () {
    $job = new FailedJob(['payload' => json_encode(['job' => 'Illuminate\\Queue\\CallQueuedHandler@call'])]);

    expect($job->jobName())->toBe('Illuminate\\Queue\\CallQueuedHandler@call');
});

it('falls back to a generic label for an unparsable payload', function () {
    $job = new FailedJob(['payload' => 'not json']);

    expect($job->jobName())->toBe(__('baobab::admin.queues.unknown_job'));
});

it('truncates the exception to its first line', function () {
    $job = new FailedJob(['exception' => "RuntimeException: Something broke\n#0 /app/Foo.php(12): bar()\n#1 {main}"]);

    expect($job->exceptionSummary())->toBe('RuntimeException: Something broke');
});
