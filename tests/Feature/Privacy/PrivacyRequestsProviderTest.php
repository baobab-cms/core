<?php

use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\ErasePersonalData;
use Baobab\Privacy\Actions\ExecutePersonalDataExport;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\Providers\PrivacyRequestsProvider;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    config()->set('baobab.exports.disk', 'local');
    Queue::fake();
});

it('is registered as the seventh core provider', function () {
    expect(app(PrivacyRegistry::class)->all())->toHaveKey('core.privacy_requests');
});

it('does not locate the request currently running, so a subject without data still gets "no data"', function () {
    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('lonely@example.com'));

    expect((new PrivacyRequestsProvider)->locate($request->subject()))->toBeFalse();

    app(ExecutePersonalDataExport::class)($request);

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::Failed);
});

it('locates finished requests and exports them without the archive path or the password', function () {
    User::create(['name' => 'Past', 'email' => 'past@example.com', 'password' => 'secret']);
    $first = app(CreatePrivacyRequest::class)(Subject::forEmail('past@example.com'));
    app(ExecutePersonalDataExport::class)($first);

    $second = app(CreatePrivacyRequest::class)(Subject::forEmail('past@example.com'));
    app(ExecutePersonalDataExport::class)($second);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($second->refresh()->file_path));
    $zip->setPassword((string) $second->password);
    $data = (string) $zip->getFromName('core.privacy_requests/data.json');

    expect($data)->toContain('"type": "export"')
        ->and($data)->not->toContain('privacy-exports/')
        ->and($data)->not->toContain((string) $second->password)
        ->and(json_decode($data, true)['requests'])->toHaveCount(1);
});

it('pseudonymises the address, deletes the archive and the password, keeps the row on erasure', function () {
    $user = User::create(['name' => 'Erased', 'email' => 'erased-pr@example.com', 'password' => 'secret']);
    $request = app(CreatePrivacyRequest::class)(Subject::forUser($user));
    app(ExecutePersonalDataExport::class)($request);
    $request->refresh();
    $path = $request->file_path;
    Storage::disk('local')->assertExists($path);

    $result = app(ErasePersonalData::class)(Subject::forUser($user));

    $request->refresh();
    expect($result->reports)->toHaveKey('core.privacy_requests')
        ->and($request->subject_email)->toBe(Pseudonym::address('erased-pr@example.com'))
        ->and($request->file_path)->toBeNull()
        ->and($request->password)->toBeNull()
        ->and(PrivacyRequest::query()->whereKey($request->id)->exists())->toBeTrue();

    Storage::disk('local')->assertMissing($path);
});
