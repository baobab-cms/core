<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\ExecutePersonalDataExport;
use Baobab\Privacy\Actions\PurgeExpiredPrivacyExports;
use Baobab\Privacy\Actions\RevealPrivacyRequestPassword;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Scheduler\SchedulerRegistrar;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('baobab.exports.disk', 'local');
});

/** Une demande d'export terminée, archive posée sur le disque fake. */
function completedExportRequest(string $email = 'requester@example.com'): PrivacyRequest
{
    User::create(['name' => 'Requester', 'email' => $email, 'password' => 'secret']);

    $request = app(CreatePrivacyRequest::class)(Subject::forEmail($email));
    app(ExecutePersonalDataExport::class)($request);

    return $request->refresh();
}

it('creates a pending export request and queues its job on baobab-low', function () {
    Queue::fake();
    $admin = User::create(['name' => 'Admin', 'email' => 'admin-pr@example.com', 'password' => 'secret']);

    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('Person@Example.com'), $admin);

    expect($request->type)->toBe(PrivacyRequestType::Export)
        ->and($request->status)->toBe(PrivacyRequestStatus::Pending)
        ->and($request->subject_email)->toBe('person@example.com')
        ->and($request->origin)->toBe('admin')
        ->and($request->requested_by)->toBe($admin->id)
        ->and($request->uuid)->not->toBeEmpty();

    Queue::assertPushedOn('baobab-low', RunPersonalDataExportJob::class);

    $entry = AuditEntry::query()->where('action', 'privacy.request.created')->first();
    expect($entry)->not->toBeNull()
        ->and(json_encode($entry->data))->not->toContain('person@example.com');
});

it('runs the export: archive on the disk, password encrypted at rest, expiry set', function () {
    Queue::fake();
    $request = completedExportRequest();

    expect($request->status)->toBe(PrivacyRequestStatus::Completed)
        ->and($request->file_path)->toBe("privacy-exports/{$request->uuid}.zip")
        ->and($request->file_size)->toBeGreaterThan(0)
        ->and($request->expires_at->isFuture())->toBeTrue()
        ->and($request->password)->not->toBeEmpty();

    Storage::disk('local')->assertExists($request->file_path);

    $raw = (string) DB::table('privacy_requests')->where('id', $request->id)->value('password');
    expect($raw)->not->toBe($request->password);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($request->file_path));
    $zip->setPassword((string) $request->password);
    expect($zip->getFromName('core.users/data.json'))->toContain('requester@example.com');
});

it('e-mails a signed link but never the password', function () {
    Queue::fake();
    $request = completedExportRequest();

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($request): bool {
        return $mail->to === 'requester@example.com'
            && $mail->templateKey === 'core.privacy_export_ready'
            && str_contains($mail->html, "/baobab/privacy/exports/{$request->uuid}?signature=")
            && ! str_contains($mail->html, (string) $request->password)
            && ! str_contains($mail->text, (string) $request->password);
    });

    expect(MailLogEntry::query()->where('template_key', 'core.privacy_export_ready')->exists())->toBeTrue();
});

it('marks the request failed with a readable reason when the subject holds no data', function () {
    Queue::fake();
    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('nobody@example.com'));

    app(ExecutePersonalDataExport::class)($request);

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::Failed)
        ->and($request->error_message)->not->toBeEmpty()
        ->and($request->file_path)->toBeNull();

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('does not run a request twice', function () {
    Queue::fake();
    $request = completedExportRequest();

    expect(app(ExecutePersonalDataExport::class)($request))->toBeFalse();
});

it('reveals the password exactly once and audits it without the secret', function () {
    Queue::fake();
    $request = completedExportRequest();
    $password = $request->password;

    $first = app(RevealPrivacyRequestPassword::class)($request);
    $second = app(RevealPrivacyRequestPassword::class)($request->refresh());

    expect($first)->toBe($password)
        ->and($second)->toBeNull()
        ->and($request->refresh()->password)->toBeNull();

    $entries = AuditEntry::query()->where('action', 'privacy.export.password_revealed')->get();
    expect($entries)->toHaveCount(1)
        ->and(json_encode($entries->first()->data))->not->toContain($password);
});

it('never reveals a password once the archive has expired', function () {
    Queue::fake();
    $request = completedExportRequest();
    $request->update(['expires_at' => now()->subMinute()]);

    expect(app(RevealPrivacyRequestPassword::class)($request))->toBeNull();
});

it('purges expired archives and unread passwords but keeps the row', function () {
    Queue::fake();
    $expired = completedExportRequest('expired@example.com');
    $fresh = completedExportRequest('fresh@example.com');
    $expired->update(['expires_at' => now()->subMinute()]);

    $count = app(PurgeExpiredPrivacyExports::class)();

    expect($count)->toBe(1)
        ->and($expired->refresh()->status)->toBe(PrivacyRequestStatus::Expired)
        ->and($expired->file_path)->toBeNull()
        ->and($expired->password)->toBeNull()
        ->and($fresh->refresh()->status)->toBe(PrivacyRequestStatus::Completed);

    Storage::disk('local')->assertMissing("privacy-exports/{$expired->uuid}.zip");
    Storage::disk('local')->assertExists("privacy-exports/{$fresh->uuid}.zip");
    $this->artisan('baobab:privacy:purge-exports')->assertSuccessful();
});

it('serves the archive through a signed link and audits each download', function () {
    Queue::fake();
    $request = completedExportRequest();
    $url = URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $request->uuid]);

    $this->get($url)->assertOk()->assertDownload();
    $this->get($url)->assertOk();

    expect(AuditEntry::query()->where('action', 'privacy.export.downloaded')->count())->toBe(2);
});

it('refuses an unsigned or tampered download link', function () {
    Queue::fake();
    $request = completedExportRequest();

    $this->get("/baobab/privacy/exports/{$request->uuid}")->assertForbidden();
    $this->get(URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $request->uuid]).'x')->assertForbidden();
});

it('answers 410 once the archive is past its expiry, purged or not', function () {
    Queue::fake();
    $request = completedExportRequest();
    $url = URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $request->uuid]);

    $request->update(['expires_at' => now()->subMinute()]);
    $this->get($url)->assertStatus(410);

    app(PurgeExpiredPrivacyExports::class)();
    $this->get($url)->assertStatus(410);
});

it('answers 404 for a request that has no archive', function () {
    Queue::fake();
    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('pending@example.com'));

    $this->get(URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $request->uuid]))->assertNotFound();
});

it('is scheduled daily among the core tasks', function () {
    $keys = array_map(
        fn ($task) => $task->taskKey,
        app(SchedulerRegistrar::class)->describeTasks(),
    );

    expect($keys)->toContain('baobab.privacy.purge-exports');
});
