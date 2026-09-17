<?php

use Baobab\Audit\Models\AuditEntry;
use Illuminate\Support\Facades\Artisan;

it('purges audit entries older than the configured retention and audits the purge', function () {
    config(['baobab.audit.retention_days' => 30]);

    $old = AuditEntry::create(['action' => 'content.saved']);
    $old->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

    AuditEntry::create(['action' => 'content.saved']);

    Artisan::call('baobab:audit:purge');

    expect(AuditEntry::where('id', $old->id)->exists())->toBeFalse()
        ->and(AuditEntry::where('action', 'content.saved')->count())->toBe(1)
        ->and(AuditEntry::where('action', 'audit.purged')->where('data->count', 1)->exists())->toBeTrue();
});

it('does not audit a purge that removed nothing', function () {
    config(['baobab.audit.retention_days' => 365]);

    AuditEntry::create(['action' => 'content.saved']);

    Artisan::call('baobab:audit:purge');

    expect(AuditEntry::where('action', 'audit.purged')->exists())->toBeFalse();
});
