<?php

use Baobab\System\HealthChecks\BackupsFreshnessCheck;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    config(['baobab.backups.destinations' => ['local']]);
});

it('warns when no backup has ever been created', function () {
    $result = app(BackupsFreshnessCheck::class)->run();

    expect($result->status->value)->toBe('warning');
});

it('reports ok when the most recent backup is within the expected window', function () {
    Storage::disk('local')->put(config('baobab.backups.name').'/recent.zip', 'x');

    $result = app(BackupsFreshnessCheck::class)->run();

    expect($result->status->value)->toBe('ok');
});

it('fails when the most recent backup is older than the expected window', function () {
    config(['baobab.health.backups.expected_within_hours' => 1]);
    Storage::disk('local')->put(config('baobab.backups.name').'/old.zip', 'x');
    touch(Storage::disk('local')->path(config('baobab.backups.name').'/old.zip'), now()->subHours(3)->timestamp);

    $result = app(BackupsFreshnessCheck::class)->run();

    expect($result->status->value)->toBe('failed');
});
