<?php

use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Support\RichTextMediaUsageScanner;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function richTextScannerActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "RichText Scanner Actor {$counter}",
        'email' => "richtext-scanner-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('resolves a media referenced by its original URL inside an <img> tag', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        richTextScannerActor(),
    );

    $html = "<p>Texte</p><img src=\"{$media->url()}\" alt=\"\">";

    $found = app(RichTextMediaUsageScanner::class)->scan($html);

    expect($found)->toHaveCount(1)
        ->and($found->first()?->id)->toBe($media->id);
});

it('resolves a media referenced by a preset variant or an edited path', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        richTextScannerActor(),
    );

    $variantUrl = Storage::disk('public')->url("media/2026/07/{$media->uuid}-thumb.webp");
    $editedUrl = Storage::disk('public')->url("media/2026/07/{$media->uuid}-edited.jpg");

    $variantFound = app(RichTextMediaUsageScanner::class)->scan("<img src=\"{$variantUrl}\">");
    $editedFound = app(RichTextMediaUsageScanner::class)->scan("<img src=\"{$editedUrl}\">");

    expect($variantFound->first()?->id)->toBe($media->id)
        ->and($editedFound->first()?->id)->toBe($media->id);
});

it('ignores non-media images and returns an empty collection when nothing matches', function () {
    $found = app(RichTextMediaUsageScanner::class)->scan('<p>Aucune image</p><img src="https://example.com/logo.png">');

    expect($found)->toHaveCount(0);
});

it('deduplicates when the same media is referenced more than once', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        richTextScannerActor(),
    );

    $html = "<img src=\"{$media->url()}\"><img src=\"{$media->url()}\">";

    $found = app(RichTextMediaUsageScanner::class)->scan($html);

    expect($found)->toHaveCount(1);
});
