<?php

use Baobab\System\HealthChecks\StorageCheck;

it('reports ok when storage, bootstrap/cache and public are all writable', function () {
    $result = app(StorageCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});
