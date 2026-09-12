<?php

use Baobab\Facades\Hook;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Baobab\Media\Exceptions\MediaTooLargeException;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function uploadActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Uploader {$counter}",
        'email' => "uploader-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('uploads a valid JPEG and stores real file with a matching checksum', function () {
    $path = createTestJpeg(40, 20);
    $file = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    $actor = uploadActor();

    $media = app(UploadMedia::class)($file, $actor);

    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->author_id)->toBe($actor->id)
        ->and($media->width)->toBe(40)
        ->and($media->height)->toBe(20)
        ->and($media->uuid)->not->toBeNull();

    Storage::disk('public')->assertExists($media->path);

    $stored = Storage::disk('public')->get($media->path);
    expect($media->checksum)->toBe(hash('sha256', $stored));
});

it('rejects a file whose real content does not match the allowed MIME whitelist', function () {
    $path = sys_get_temp_dir().'/baobab-test-fake.jpg';
    file_put_contents($path, 'this is plain text pretending to be a jpeg');
    $file = new UploadedFile($path, 'fake.jpg', 'image/jpeg', null, true);

    expect(fn () => app(UploadMedia::class)($file, uploadActor()))
        ->toThrow(InvalidMediaUploadException::class);
});

it('rejects a file larger than the configured limit', function () {
    config(['baobab.media.max_upload_size' => 10]);

    $path = createTestJpeg(40, 20);
    $file = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);

    expect(fn () => app(UploadMedia::class)($file, uploadActor()))
        ->toThrow(MediaTooLargeException::class);
});

it('lets the baobab.media.uploading.max_size filter raise the limit for a given actor', function () {
    config(['baobab.media.max_upload_size' => 10]);

    Hook::modify('baobab.media.uploading.max_size', fn (int $default) => 10_000_000);

    $path = createTestJpeg(40, 20);
    $file = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    expect($media)->not->toBeNull();
});

it('sanitizes a malicious SVG before storing it', function () {
    $svgPath = sys_get_temp_dir().'/baobab-test-malicious.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect onload="alert(2)" width="1" height="1" /></svg>');
    $file = new UploadedFile($svgPath, 'malicious.svg', 'image/svg+xml', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    $stored = Storage::disk('public')->get($media->path);

    expect($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('onload');
});

it('strips dangerous SVG elements regardless of tag case', function () {
    $svgPath = sys_get_temp_dir().'/baobab-test-uppercase-script.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><SCRIPT>alert(1)</SCRIPT></svg>');
    $file = new UploadedFile($svgPath, 'uppercase.svg', 'image/svg+xml', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    $stored = Storage::disk('public')->get($media->path);

    expect(mb_strtolower((string) $stored))->not->toContain('<script');
});

it('strips SMIL set/animate elements that could recreate an event handler', function () {
    $svgPath = sys_get_temp_dir().'/baobab-test-smil.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1">'
        .'<set attributeName="onmouseover" to="alert(1)" />'
        .'<animate attributeName="onclick" from="0" to="alert(2)" />'
        .'</rect></svg>');
    $file = new UploadedFile($svgPath, 'smil.svg', 'image/svg+xml', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    $stored = Storage::disk('public')->get($media->path);

    expect($stored)->not->toContain('<set')
        ->and($stored)->not->toContain('<animate');
});

it('strips foreignObject elements embedding foreign markup', function () {
    $svgPath = sys_get_temp_dir().'/baobab-test-foreignobject.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject>'
        .'<body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body>'
        .'</foreignObject></svg>');
    $file = new UploadedFile($svgPath, 'foreign.svg', 'image/svg+xml', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    $stored = Storage::disk('public')->get($media->path);

    expect(mb_strtolower((string) $stored))->not->toContain('foreignobject')
        ->and($stored)->not->toContain('<script');
});

it('strips use elements entirely and blocks data:/external href schemes on remaining attributes', function () {
    $svgPath = sys_get_temp_dir().'/baobab-test-use-href.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<use xlink:href="https://evil.example/x.svg#y" />'
        .'<a href="data:text/html,%3Cscript%3Ealert(1)%3C/script%3E"><rect width="1" height="1" /></a>'
        .'<rect id="icon" width="1" height="1" /><use href="#icon" />'
        .'</svg>');
    $file = new UploadedFile($svgPath, 'use-href.svg', 'image/svg+xml', null, true);

    $media = app(UploadMedia::class)($file, uploadActor());

    $stored = Storage::disk('public')->get($media->path);

    expect(mb_strtolower((string) $stored))->not->toContain('<use')
        ->and($stored)->not->toContain('data:text/html')
        ->and($stored)->not->toContain('evil.example');
});

it('strips EXIF GPS data and applies orientation when uploading a JPEG', function () {
    // Orientation 6 = "rotate 90 CW" : une source paysage (40x20) doit devenir portrait (20x40).
    $path = createTestJpegWithExif(orientation: 6, width: 40, height: 20);

    $exifBefore = @exif_read_data($path);
    expect($exifBefore)->toBeArray()
        ->and($exifBefore['GPSVersion'] ?? null)->not->toBeNull();

    $file = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    $media = app(UploadMedia::class)($file, uploadActor());

    expect($media->width)->toBe(20)
        ->and($media->height)->toBe(40);

    $storedPath = Storage::disk('public')->path($media->path);
    $exifAfter = @exif_read_data($storedPath);

    expect(is_array($exifAfter) ? ($exifAfter['GPSVersion'] ?? null) : null)->toBeNull();
});

it('dispatches GenerateMediaConversions for a raster image but not for an SVG', function () {
    Queue::fake();

    $jpeg = new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true);
    $jpegMedia = app(UploadMedia::class)($jpeg, uploadActor());

    Queue::assertPushed(GenerateMediaConversions::class);

    $svgPath = sys_get_temp_dir().'/baobab-test-no-conversions.svg';
    file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" /></svg>');
    $svg = new UploadedFile($svgPath, 'icon.svg', 'image/svg+xml', null, true);

    Queue::fake();
    app(UploadMedia::class)($svg, uploadActor());

    Queue::assertNotPushed(GenerateMediaConversions::class);

    expect($jpegMedia)->not->toBeNull();
});
