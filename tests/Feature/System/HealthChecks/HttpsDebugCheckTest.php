<?php

use Baobab\System\HealthChecks\HttpsDebugCheck;

it('is not applicable outside production, even with debug on and no HTTPS', function () {
    config(['app.env' => 'testing', 'app.debug' => true, 'app.url' => 'http://localhost']);

    $result = app(HttpsDebugCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});

it('fails in production when debug is on or HTTPS is off', function () {
    config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://example.test']);

    $result = app(HttpsDebugCheck::class)->run();

    expect($result->status->value)->toBe('failed')
        ->and($result->notificationMessage)->toContain('APP_DEBUG')
        ->and($result->notificationMessage)->toContain('HTTPS');
});

it('reports ok in production once debug is off and HTTPS is active', function () {
    config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://example.test']);

    $result = app(HttpsDebugCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});
