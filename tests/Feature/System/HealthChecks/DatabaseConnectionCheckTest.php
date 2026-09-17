<?php

use Baobab\System\HealthChecks\DatabaseConnectionCheck;

it('reports ok when the database connects within the latency threshold', function () {
    $result = app(DatabaseConnectionCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});

it('warns when the query takes longer than the configured threshold', function () {
    config(['baobab.health.database.latency_warning_ms' => -1]);

    $result = app(DatabaseConnectionCheck::class)->run();

    expect($result->status->value)->toBe('warning');
});
