<?php

use Baobab\Media\Actions\UpdateMediaMetadata;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function metadataActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Metadata Actor {$counter}",
        'email' => "metadata-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('updates title, alt, caption and description', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        metadataActor(),
    );

    $updated = app(UpdateMediaMetadata::class)($media, [
        'title' => 'Coucher de soleil',
        'alt' => 'Un coucher de soleil orange sur la mer',
        'caption' => 'Photo prise à Étretat',
        'description' => 'Une longue description.',
    ]);

    expect($updated->title)->toBe('Coucher de soleil')
        ->and($updated->alt)->toBe('Un coucher de soleil orange sur la mer')
        ->and($updated->caption)->toBe('Photo prise à Étretat')
        ->and($updated->description)->toBe('Une longue description.');

    expect($media->fresh()?->alt)->toBe('Un coucher de soleil orange sur la mer');
});

it('clears a field when it is omitted from the metadata array', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        metadataActor(),
        ['alt' => 'Texte alternatif initial'],
    );

    app(UpdateMediaMetadata::class)($media, ['title' => 'Nouveau titre']);

    expect($media->fresh()?->alt)->toBeNull()
        ->and($media->fresh()?->title)->toBe('Nouveau titre');
});
