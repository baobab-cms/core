<?php

use Baobab\Media\Actions\DeleteMedia;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function purgeTrashCommandActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Purge Trash Command Actor {$counter}",
        'email' => "purge-trash-command-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('purges media trashed longer ago than the configured retention', function () {
    config(['baobab.media.trash_retention_days' => 30]);

    $old = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'old.jpg', 'image/jpeg', null, true),
        purgeTrashCommandActor(),
    );
    app(DeleteMedia::class)($old);
    $old->fresh()?->forceFill(['deleted_at' => now()->subDays(31)])->save();

    $recent = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'recent.jpg', 'image/jpeg', null, true),
        purgeTrashCommandActor(),
    );
    app(DeleteMedia::class)($recent);
    $recent->fresh()?->forceFill(['deleted_at' => now()->subDays(5)])->save();

    Artisan::call('media:purge-trash');

    expect(Media::withTrashed()->find($old->id))->toBeNull()
        ->and(Media::withTrashed()->find($recent->id))->not->toBeNull();
});
