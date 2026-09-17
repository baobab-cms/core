<?php

use Baobab\System\HealthChecks\PhpRequirementsCheck;

it('reports ok on a machine meeting the v1 floor, and surfaces the running PHP version', function () {
    $result = app(PhpRequirementsCheck::class)->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->shortSummary)->toBe(PHP_VERSION);
});
