<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\ExecutePersonalDataExport;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * @param  list<string>  $permissions
 */
function privacyRequestsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Requests Actor {$counter}",
        'email' => "privacy-requests-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('baobab.exports.disk', 'local');
});

it('denies every requests route without baobab.privacy.requests.manage', function () {
    $actor = privacyRequestsActor([]);
    $request = PrivacyRequest::create(['type' => 'export', 'status' => 'pending', 'subject_email' => 'a@example.com']);

    $this->actingAs($actor, 'baobab')->get(route('admin.privacy.requests.index'))->assertForbidden();
    $this->actingAs($actor, 'baobab')->post(route('admin.privacy.requests.store'), ['subject' => 'a@example.com'])->assertForbidden();
    $this->actingAs($actor, 'baobab')->get(route('admin.privacy.requests.show', $request))->assertForbidden();
    $this->actingAs($actor, 'baobab')->post(route('admin.privacy.requests.reveal-password', $request))->assertForbidden();
});

it('lists the requests', function () {
    Queue::fake();
    app(CreatePrivacyRequest::class)(Subject::forEmail('listed@example.com'));

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->get(route('admin.privacy.requests.index'))
        ->assertOk()
        ->assertSee('listed@example.com')
        ->assertSee(__('baobab::admin.privacy_requests.status_pending'));
});

it('creates an export request from an e-mail address and queues it', function () {
    Queue::fake();

    $response = $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.store'), ['subject' => 'Someone@Example.com']);

    $request = PrivacyRequest::query()->firstOrFail();
    $response->assertRedirect(route('admin.privacy.requests.show', $request));

    expect($request->subject_email)->toBe('someone@example.com');
    Queue::assertPushedOn('baobab-low', RunPersonalDataExportJob::class);
});

it('creates an export request from an account identifier', function () {
    Queue::fake();
    $target = privacyRequestsActor([]);

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.store'), ['subject' => (string) $target->id])
        ->assertRedirect();

    expect(PrivacyRequest::query()->firstOrFail()->subject_user_id)->toBe($target->id);
});

it('rejects an unknown account identifier', function () {
    Queue::fake();

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.store'), ['subject' => '99999'])
        ->assertSessionHasErrors('subject');

    expect(PrivacyRequest::query()->count())->toBe(0);
});

it('shows the password once on the detail screen, then never again', function () {
    Queue::fake();
    $target = privacyRequestsActor([]);
    $request = app(CreatePrivacyRequest::class)(Subject::forUser($target));
    app(ExecutePersonalDataExport::class)($request);
    $password = $request->refresh()->password;
    $actor = privacyRequestsActor(['baobab.privacy.requests.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.privacy.requests.show', $request))
        ->assertOk()
        ->assertDontSee($password)
        ->assertSee(__('baobab::admin.privacy_requests.reveal'));

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.privacy.requests.reveal-password', $request))
        ->assertOk()
        ->assertSee($password);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.privacy.requests.show', $request))
        ->assertOk()
        ->assertDontSee($password)
        ->assertSee(__('baobab::admin.privacy_requests.password_gone'));

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.privacy.requests.reveal-password', $request))
        ->assertOk()
        ->assertDontSee($password);
});

it('shows the failure reason of a request', function () {
    Queue::fake();
    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('ghost@example.com'));
    app(ExecutePersonalDataExport::class)($request);

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->get(route('admin.privacy.requests.show', $request))
        ->assertOk()
        ->assertSee(__('baobab::admin.privacy_requests.status_failed'))
        ->assertSee($request->refresh()->error_message);
});

it('adds the requests entry to the sidebar for those who may manage them', function () {
    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->get(route('admin.privacy.requests.index'))
        ->assertSee(__('baobab::admin.sidebar.privacy_requests'));
});

it('seeds the permission for the admin role only', function () {
    $admin = Role::where('name', 'admin')->where('guard_name', 'baobab')->first();
    $editor = Role::where('name', 'editor')->where('guard_name', 'baobab')->first();

    expect($admin?->hasPermissionTo('baobab.privacy.requests.manage'))->toBeTrue()
        ->and($editor?->hasPermissionTo('baobab.privacy.requests.manage'))->toBeFalse();
});

it('schedules an erasure request from the screen and queues nothing', function () {
    Queue::fake();
    $target = privacyRequestsActor([]);

    $response = $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.store'), ['subject' => (string) $target->id, 'type' => 'erasure']);

    $request = PrivacyRequest::query()->firstOrFail();
    $response->assertRedirect(route('admin.privacy.requests.show', $request));

    expect($request->type)->toBe(PrivacyRequestType::Erasure)
        ->and($request->status)->toBe(PrivacyRequestStatus::Scheduled);
    Queue::assertNothingPushed();
});

it('rejects an unknown request type', function () {
    Queue::fake();

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.store'), ['subject' => 'a@example.com', 'type' => 'bogus'])
        ->assertSessionHasErrors('type');
});

it('reports a duplicate scheduled erasure as a form error', function () {
    Queue::fake();
    $target = privacyRequestsActor([]);
    $actor = privacyRequestsActor(['baobab.privacy.requests.manage']);
    $payload = ['subject' => (string) $target->id, 'type' => 'erasure'];

    $this->actingAs($actor, 'baobab')->post(route('admin.privacy.requests.store'), $payload);
    $this->actingAs($actor, 'baobab')->post(route('admin.privacy.requests.store'), $payload)
        ->assertSessionHasErrors('subject');

    expect(PrivacyRequest::query()->count())->toBe(1);
});

it('offers the cancel button on a scheduled erasure only, and cancels it', function () {
    Queue::fake();
    $target = privacyRequestsActor([]);
    $actor = privacyRequestsActor(['baobab.privacy.requests.manage']);
    $request = app(CreatePrivacyRequest::class)(Subject::forUser($target), $actor, 'admin', PrivacyRequestType::Erasure);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.privacy.requests.show', $request))
        ->assertOk()
        ->assertSee(__('baobab::admin.privacy_requests.cancel'))
        ->assertDontSee('Exécuter maintenant');

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.privacy.requests.cancel', $request))
        ->assertRedirect(route('admin.privacy.requests.show', $request));

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::Cancelled);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.privacy.requests.show', $request))
        ->assertOk()
        ->assertDontSee(__('baobab::admin.privacy_requests.cancel_title'));
});

it('refuses to cancel an export request', function () {
    Queue::fake();
    $export = app(CreatePrivacyRequest::class)(Subject::forEmail('not-cancellable@example.com'));

    $this->actingAs(privacyRequestsActor(['baobab.privacy.requests.manage']), 'baobab')
        ->post(route('admin.privacy.requests.cancel', $export))
        ->assertRedirect(route('admin.privacy.requests.show', $export));

    expect($export->refresh()->status)->toBe(PrivacyRequestStatus::Pending);
});
