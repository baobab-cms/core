<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Privacy\Actions\CancelPrivacyRequest;
use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\DispatchDueErasures;
use Baobab\Privacy\Actions\ExecutePersonalDataErasure;
use Baobab\Privacy\Exceptions\ErasureAlreadyScheduledException;
use Baobab\Privacy\Exceptions\RequestNotCancellableException;
use Baobab\Privacy\Jobs\RunPersonalDataErasureJob;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Scheduler\SchedulerRegistrar;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

function erasureSubjectUser(string $email): User
{
    return User::create(['name' => 'Erasure Subject', 'email' => $email, 'password' => 'secret']);
}

function scheduleErasure(Subject $subject, ?User $by = null): PrivacyRequest
{
    return app(CreatePrivacyRequest::class)($subject, $by, 'admin', PrivacyRequestType::Erasure);
}

it('creates a scheduled erasure after the grace delay and queues nothing', function () {
    Queue::fake();
    config()->set('baobab.privacy.erasure_grace_days', 15);
    $user = erasureSubjectUser('erasure-create@example.com');

    $request = scheduleErasure(new Subject($user->id, $user->email));

    expect($request->type)->toBe(PrivacyRequestType::Erasure)
        ->and($request->status)->toBe(PrivacyRequestStatus::Scheduled)
        ->and($request->scheduled_for->isSameDay(now()->addDays(15)))->toBeTrue()
        ->and($request->isCancellable())->toBeTrue();

    Queue::assertNothingPushed();

    $entry = AuditEntry::query()->where('action', 'privacy.request.created')->firstOrFail();
    expect(json_encode($entry->data))->not->toContain('erasure-create@example.com');
});

it('keeps creating exports without a schedule', function () {
    Queue::fake();

    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('export-still@example.com'));

    expect($request->status)->toBe(PrivacyRequestStatus::Pending)
        ->and($request->scheduled_for)->toBeNull()
        ->and($request->isCancellable())->toBeFalse();

    Queue::assertPushed(RunPersonalDataExportJob::class);
});

it('refuses a second scheduled erasure for the same subject, by account or by e-mail', function () {
    $user = erasureSubjectUser('erasure-dup@example.com');
    scheduleErasure(new Subject($user->id, $user->email));

    expect(fn () => scheduleErasure(Subject::forEmail('Erasure-Dup@example.com')))
        ->toThrow(ErasureAlreadyScheduledException::class)
        ->and(PrivacyRequest::query()->count())->toBe(1);
});

it('allows a new erasure once the previous one is cancelled', function () {
    $user = erasureSubjectUser('erasure-again@example.com');
    $subject = new Subject($user->id, $user->email);
    app(CancelPrivacyRequest::class)(scheduleErasure($subject));

    expect(scheduleErasure($subject)->status)->toBe(PrivacyRequestStatus::Scheduled);
});

it('refuses to schedule the erasure of the last super-admin', function () {
    $user = erasureSubjectUser('erasure-solo@example.com');
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));

    expect(fn () => scheduleErasure(new Subject($user->id, $user->email)))
        ->toThrow(AdminLockoutException::class)
        ->and(PrivacyRequest::query()->count())->toBe(0);
});

it('cancels a scheduled erasure, audits it and never executes it', function () {
    Queue::fake();
    config()->set('baobab.privacy.erasure_grace_days', 0);
    $user = erasureSubjectUser('erasure-cancel@example.com');
    $request = scheduleErasure(new Subject($user->id, $user->email));

    $cancelled = app(CancelPrivacyRequest::class)($request);

    expect($cancelled->status)->toBe(PrivacyRequestStatus::Cancelled)
        ->and($cancelled->finished_at)->not->toBeNull()
        ->and(AuditEntry::query()->where('action', 'privacy.request.cancelled')->exists())->toBeTrue()
        ->and(app(DispatchDueErasures::class)())->toBe(0);

    Queue::assertNothingPushed();
    expect(User::findOrFail($user->id)->email)->toBe('erasure-cancel@example.com');
});

it('cannot cancel what is not a scheduled erasure', function () {
    Queue::fake();
    $export = app(CreatePrivacyRequest::class)(Subject::forEmail('cancel-export@example.com'));
    $user = erasureSubjectUser('erasure-cancel-twice@example.com');
    $erasure = scheduleErasure(new Subject($user->id, $user->email));
    app(CancelPrivacyRequest::class)($erasure);

    expect(fn () => app(CancelPrivacyRequest::class)($export))->toThrow(RequestNotCancellableException::class)
        ->and(fn () => app(CancelPrivacyRequest::class)($erasure->refresh()))->toThrow(RequestNotCancellableException::class);
});

it('dispatches only due erasures, once, marking them pending', function () {
    Queue::fake();
    $due = erasureSubjectUser('erasure-due@example.com');
    $later = erasureSubjectUser('erasure-later@example.com');

    $dueRequest = scheduleErasure(new Subject($due->id, $due->email));
    $dueRequest->update(['scheduled_for' => now()->subMinute()]);
    $laterRequest = scheduleErasure(new Subject($later->id, $later->email));

    expect(app(DispatchDueErasures::class)())->toBe(1)
        ->and(app(DispatchDueErasures::class)())->toBe(0)
        ->and($dueRequest->refresh()->status)->toBe(PrivacyRequestStatus::Pending)
        ->and($laterRequest->refresh()->status)->toBe(PrivacyRequestStatus::Scheduled);

    Queue::assertPushedOn('baobab-low', RunPersonalDataErasureJob::class);
});

it('executes a due erasure: account anonymised, request completed, report in the audit', function () {
    $user = erasureSubjectUser('erasure-run@example.com');
    $request = scheduleErasure(new Subject($user->id, $user->email));
    $request->update(['scheduled_for' => now()->subMinute()]);

    Queue::fake();
    app(DispatchDueErasures::class)();

    (new RunPersonalDataErasureJob($request->id))->handle(app(ExecutePersonalDataErasure::class));

    $request->refresh();

    expect($request->status)->toBe(PrivacyRequestStatus::Completed)
        ->and($request->finished_at)->not->toBeNull()
        ->and($request->subject_email)->toEndWith('@erased.invalid')
        ->and(User::findOrFail($user->id)->email)->toEndWith('@erased.invalid')
        ->and(AuditEntry::query()->where('action', 'privacy.erased')->exists())->toBeTrue();
});

it('only executes a pending erasure', function () {
    $user = erasureSubjectUser('erasure-guard@example.com');
    $request = scheduleErasure(new Subject($user->id, $user->email));

    expect(app(ExecutePersonalDataErasure::class)($request))->toBeFalse()
        ->and($request->refresh()->status)->toBe(PrivacyRequestStatus::Scheduled)
        ->and(User::findOrFail($user->id)->email)->toBe('erasure-guard@example.com');
});

it('fails with its reason when the subject became the last super-admin during the delay', function () {
    $user = erasureSubjectUser('erasure-late-admin@example.com');
    $request = scheduleErasure(new Subject($user->id, $user->email));
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));
    $request->update(['status' => PrivacyRequestStatus::Pending]);

    expect(app(ExecutePersonalDataErasure::class)($request))->toBeFalse();

    $request->refresh();

    expect($request->status)->toBe(PrivacyRequestStatus::Failed)
        ->and($request->error_message)->toContain('dernier super-administrateur')
        ->and(User::findOrFail($user->id)->email)->toBe('erasure-late-admin@example.com');
});

it('fails with a readable reason when the subject holds no data', function () {
    $request = scheduleErasure(Subject::forEmail('erasure-nobody@example.com'));
    $request->update(['status' => PrivacyRequestStatus::Pending]);

    expect(app(ExecutePersonalDataErasure::class)($request))->toBeFalse()
        ->and($request->refresh()->status)->toBe(PrivacyRequestStatus::Failed)
        ->and($request->error_message)->toContain('Aucune donnée personnelle');
});

it('runs due erasures through the artisan command', function () {
    Queue::fake();
    $user = erasureSubjectUser('erasure-cmd@example.com');
    scheduleErasure(new Subject($user->id, $user->email))->update(['scheduled_for' => now()->subMinute()]);

    $this->artisan('baobab:privacy:run-due-erasures')
        ->expectsOutputToContain('1 effacement(s) mis en file')
        ->assertSuccessful();
});

it('is scheduled hourly among the core tasks', function () {
    $task = collect(app(SchedulerRegistrar::class)->describeTasks())
        ->first(fn ($task) => $task->taskKey === 'baobab.privacy.run-due-erasures');

    expect($task)->not->toBeNull();
});
