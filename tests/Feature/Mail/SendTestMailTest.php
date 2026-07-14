<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Actions\SendTestMail;
use Baobab\Mail\Jobs\SendQueuedMail;
use Illuminate\Support\Facades\Queue;

it('sends the core.test template and records an audit entry', function () {
    Queue::fake();

    app(SendTestMail::class)('dest@example.com');

    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'dest@example.com');

    $audit = AuditEntry::where('action', 'mail.test_sent')->first();
    expect($audit->data['recipient'])->toBe('dest@example.com')
        ->and($audit->data['template'])->toBe('core.test');
});
