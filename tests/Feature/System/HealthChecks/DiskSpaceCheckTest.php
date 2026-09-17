<?php

use Baobab\System\HealthChecks\DiskSpaceCheck;

it('reports ok when disk usage is below the warning threshold', function () {
    config(['baobab.health.disk_space.warning_percent' => 100, 'baobab.health.disk_space.failing_percent' => 100]);

    $result = app(DiskSpaceCheck::class)->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->shortSummary)->toEndWith('%');
});

it('fails once used space reaches the failing threshold', function () {
    config(['baobab.health.disk_space.warning_percent' => 0, 'baobab.health.disk_space.failing_percent' => 0]);

    $result = app(DiskSpaceCheck::class)->run();

    expect($result->status->value)->toBe('failed');
});
