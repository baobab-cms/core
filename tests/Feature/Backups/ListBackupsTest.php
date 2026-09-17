<?php

use Baobab\Backups\Actions\ListBackups;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    config(['baobab.backups.destinations' => ['local']]);
});

it('returns an empty list when no backup exists', function () {
    expect(app(ListBackups::class)())->toBe([]);
});

it('lists zip files found under the configured destinations, most recent first', function () {
    $name = config('baobab.backups.name');
    Storage::disk('local')->put("{$name}/older.zip", 'x');
    touch(Storage::disk('local')->path("{$name}/older.zip"), now()->subDay()->timestamp);
    Storage::disk('local')->put("{$name}/newer.zip", 'x');

    $backups = app(ListBackups::class)();

    expect($backups)->toHaveCount(2)
        ->and($backups[0]['filename'])->toBe('newer.zip')
        ->and($backups[1]['filename'])->toBe('older.zip');
});

it('ignores non-zip files under the backup directory', function () {
    Storage::disk('local')->put(config('baobab.backups.name').'/notes.txt', 'x');

    expect(app(ListBackups::class)())->toBe([]);
});
