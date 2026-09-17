<?php

use Baobab\System\HealthChecks\DatabaseVersionCheck;

it('is not applicable on a non-MySQL/MariaDB connection, such as the sqlite test database', function () {
    $result = app(DatabaseVersionCheck::class)->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->notificationMessage)->toContain('Sans objet');
});

it('strips the MariaDB MySQL-5.5 compatibility prefix before comparing versions', function () {
    $extractVersion = (new ReflectionMethod(DatabaseVersionCheck::class, 'extractVersion'))->getClosure(app(DatabaseVersionCheck::class));

    expect($extractVersion('5.5.5-10.6.12-MariaDB'))->toBe('10.6.12')
        ->and($extractVersion('8.0.35'))->toBe('8.0.35');
});
