<?php

use Baobab\Forms\Models\FormSubmission;
use Baobab\Forms\Support\FormFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an uploaded file on the configured private disk, under form-submissions/Y/m', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf');

    $reference = app(FormFileStorage::class)->store($file);

    expect($reference['original_name'])->toBe('cv.pdf')
        ->and($reference['mime_type'])->toBe('application/pdf')
        ->and($reference['size'])->toBeInt()
        ->and($reference['stored_path'])->toStartWith('form-submissions/'.now()->format('Y/m').'/');

    Storage::disk('local')->assertExists($reference['stored_path']);
});

it('honors a disk override from config (baobab.forms.disk)', function () {
    config(['baobab.forms.disk' => 'public']);
    Storage::fake('public');

    $reference = app(FormFileStorage::class)->store(UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'));

    Storage::disk('public')->assertExists($reference['stored_path']);
});

it('deletes a stored reference from disk', function () {
    Storage::fake('local');
    $reference = app(FormFileStorage::class)->store(UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'));

    app(FormFileStorage::class)->delete($reference);

    Storage::disk('local')->assertMissing($reference['stored_path']);
});

it('does nothing when the reference has no stored_path', function () {
    Storage::fake('local');

    app(FormFileStorage::class)->delete([]);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('deletes every file field of a submission from its blueprint_snapshot, ignoring non-file fields', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-submissions/2026/09/a.pdf', 'a');
    Storage::disk('local')->put('form-submissions/2026/09/b.pdf', 'b');

    $submission = FormSubmission::make([
        'blueprint_snapshot' => [
            ['key' => 'email', 'type' => 'email'],
            ['key' => 'cv', 'type' => 'file'],
            ['key' => 'cover_letter', 'type' => 'file'],
        ],
        'payload' => [
            'email' => 'jane@example.com',
            'cv' => ['stored_path' => 'form-submissions/2026/09/a.pdf'],
            'cover_letter' => ['stored_path' => 'form-submissions/2026/09/b.pdf'],
        ],
    ]);

    app(FormFileStorage::class)->deleteForSubmission($submission);

    Storage::disk('local')->assertMissing('form-submissions/2026/09/a.pdf');
    Storage::disk('local')->assertMissing('form-submissions/2026/09/b.pdf');
});

it('does not fail deleting a submission whose file field was never filled in', function () {
    $submission = FormSubmission::make([
        'blueprint_snapshot' => [['key' => 'cv', 'type' => 'file']],
        'payload' => [],
    ]);

    app(FormFileStorage::class)->deleteForSubmission($submission);

    expect(true)->toBeTrue();
});
