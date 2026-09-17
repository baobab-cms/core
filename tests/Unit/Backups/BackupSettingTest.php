<?php

use Baobab\Backups\Models\BackupSetting;

it('falls back to config when no retention column has been set', function () {
    config([
        'baobab.backups.retention.daily' => 7,
        'baobab.backups.retention.weekly' => 4,
        'baobab.backups.retention.max_total_mb' => null,
    ]);

    $setting = new BackupSetting;

    expect($setting->retentionDaily())->toBe(7)
        ->and($setting->retentionWeekly())->toBe(4)
        ->and($setting->maxTotalSizeMb())->toBeNull();
});

it('prefers the stored column over config once one has been set', function () {
    config(['baobab.backups.retention.daily' => 7]);

    $setting = new BackupSetting(['retention_daily' => 14]);

    expect($setting->retentionDaily())->toBe(14);
});

it('defaults scheduled_enabled to true on a fresh instance', function () {
    expect((new BackupSetting)->scheduled_enabled)->toBeTrue();
});
