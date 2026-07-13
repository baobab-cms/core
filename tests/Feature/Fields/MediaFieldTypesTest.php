<?php

use Baobab\ContentTypes\Fields\Types\FileField;
use Baobab\ContentTypes\Fields\Types\ImageField;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    Storage::fake('public');
});

function mediaFieldActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Media Field Actor {$counter}",
        'email' => "media-field-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

// ── image ────────────────────────────────────────────────────────────────────

it('ImageField builds a nullable FK column to media, never cascading', function () {
    $field = new ImageField;

    expect($field->columnDefinition('photo', []))
        ->toBe("\$table->foreignId('photo')->nullable()->constrained('media')->nullOnDelete();")
        ->and($field->cast([]))->toBeNull()
        ->and($field->graphqlType([]))->toBe('Media');
});

it('ImageField accepts a real image media', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(200, 100), 'photo.jpg', 'image/jpeg', null, true),
        mediaFieldActor(),
    );

    $field = new ImageField;
    $validator = Validator::make(['photo' => $media->id], ['photo' => $field->rules('photo', [])]);

    expect($validator->fails())->toBeFalse();
});

it('ImageField rejects a media that is not an image, even without declared constraints', function () {
    $pdfPath = sys_get_temp_dir().'/baobab-test-field.pdf';
    file_put_contents($pdfPath, '%PDF-1.4 fake pdf content');
    $media = app(UploadMedia::class)(
        new UploadedFile($pdfPath, 'doc.pdf', 'application/pdf', null, true),
        mediaFieldActor(),
    );

    $field = new ImageField;
    $validator = Validator::make(['photo' => $media->id], ['photo' => $field->rules('photo', [])]);

    expect($validator->fails())->toBeTrue();
});

it('ImageField enforces declared minimum dimensions', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(50, 50), 'small.jpg', 'image/jpeg', null, true),
        mediaFieldActor(),
    );

    $field = new ImageField;
    $validator = Validator::make(
        ['photo' => $media->id],
        ['photo' => $field->rules('photo', ['min_width' => 200, 'min_height' => 200])],
    );

    expect($validator->fails())->toBeTrue();
});

it('ImageField allows a null value (optional field)', function () {
    $field = new ImageField;
    $validator = Validator::make(['photo' => null], ['photo' => $field->rules('photo', [])]);

    expect($validator->fails())->toBeFalse();
});

// ── file ─────────────────────────────────────────────────────────────────────

it('FileField builds a nullable FK column to media, never cascading', function () {
    $field = new FileField;

    expect($field->columnDefinition('attachment', []))
        ->toBe("\$table->foreignId('attachment')->nullable()->constrained('media')->nullOnDelete();")
        ->and($field->cast([]))->toBeNull()
        ->and($field->graphqlType([]))->toBe('Media');
});

it('FileField accepts any media by default', function () {
    $pdfPath = sys_get_temp_dir().'/baobab-test-field-file.pdf';
    file_put_contents($pdfPath, '%PDF-1.4 fake pdf content');
    $media = app(UploadMedia::class)(
        new UploadedFile($pdfPath, 'doc.pdf', 'application/pdf', null, true),
        mediaFieldActor(),
    );

    $field = new FileField;
    $validator = Validator::make(['attachment' => $media->id], ['attachment' => $field->rules('attachment', [])]);

    expect($validator->fails())->toBeFalse();
});

it('FileField rejects a media outside a declared mime_types whitelist', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true),
        mediaFieldActor(),
    );

    $field = new FileField;
    $validator = Validator::make(
        ['attachment' => $media->id],
        ['attachment' => $field->rules('attachment', ['mime_types' => ['application/pdf']])],
    );

    expect($validator->fails())->toBeTrue();
});

it('FileField rejects a media larger than a declared max_size', function () {
    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(400, 400), 'big.jpg', 'image/jpeg', null, true),
        mediaFieldActor(),
    );

    $field = new FileField;
    $validator = Validator::make(
        ['attachment' => $media->id],
        ['attachment' => $field->rules('attachment', ['max_size' => 10])],
    );

    expect($validator->fails())->toBeTrue();
});
