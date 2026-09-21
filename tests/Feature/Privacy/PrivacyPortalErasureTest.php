<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\ExecutePersonalDataExport;
use Baobab\Privacy\Jobs\RunPersonalDataExportJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('baobab.exports.disk', 'local');
});

/** Le lien de vérification d'un droit donné, tel que le mail le porte. */
function portalLinkFor(string $email, string $type): string
{
    $token = Crypt::encryptString(json_encode(['email' => $email, 'type' => $type]));

    return URL::temporarySignedRoute('baobab.privacy.portal.verify', now()->addMinutes(30), ['token' => $token]);
}

function portalMailTo(string $template, string $to): ?SendQueuedMail
{
    $found = null;

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($template, $to, &$found): bool {
        if ($mail->templateKey === $template && $mail->to === $to) {
            $found = $mail;

            return true;
        }

        return false;
    });

    return $found;
}

it('carries the requested right into the verification mail and the confirmation page', function () {
    Queue::fake();

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'kind@example.com', 'type' => 'erasure'])->assertRedirect();

    expect(portalMailTo('core.privacy_verification', 'kind@example.com')->html)->toContain('effacement de vos données personnelles');

    $this->get(portalLinkFor('kind@example.com', 'erasure'))
        ->assertOk()
        ->assertSee(__('baobab::privacy.portal.confirm_intro_erasure'));

    $this->from(route('baobab.privacy.portal'))
        ->post(route('baobab.privacy.portal.request'), ['email' => 'kind@example.com', 'type' => 'bogus'])
        ->assertSessionHasErrors('type');
});

it('schedules a confirmed erasure and announces it with a signed cancel link', function () {
    Queue::fake();
    User::create(['name' => 'Erasing', 'email' => 'erasing-portal@example.com', 'password' => 'secret']);

    $this->post(portalLinkFor('erasing-portal@example.com', 'erasure'))
        ->assertOk()
        ->assertSee(__('baobab::privacy.portal.confirmed_erasure'));

    $request = PrivacyRequest::query()->firstOrFail();

    expect($request->type)->toBe(PrivacyRequestType::Erasure)
        ->and($request->status)->toBe(PrivacyRequestStatus::Scheduled)
        ->and($request->origin)->toBe('portal')
        ->and($request->scheduled_for->isFuture())->toBeTrue();

    Queue::assertNotPushed(RunPersonalDataExportJob::class);

    $mail = portalMailTo('core.privacy_erasure_scheduled', 'erasing-portal@example.com');

    expect($mail->html)->toContain("/baobab/privacy/cancel/{$request->uuid}?signature=");
});

it('tells the person why an erasure is refused, on the page and by mail, without creating anything', function () {
    Queue::fake();
    $admin = User::create(['name' => 'Only Admin', 'email' => 'only-admin-portal@example.com', 'password' => 'secret']);
    app(AssignRole::class)($admin, Role::findByName('super-admin', 'baobab'));

    $this->post(portalLinkFor('only-admin-portal@example.com', 'erasure'))
        ->assertStatus(409)
        ->assertSee(__('baobab::privacy.portal.refused_last_super_admin'));

    expect(PrivacyRequest::query()->count())->toBe(0)
        ->and(portalMailTo('core.privacy_erasure_refused', 'only-admin-portal@example.com')->html)->toContain('dernier compte');

    $entry = AuditEntry::query()->where('action', 'privacy.portal.refused')->firstOrFail();

    expect($entry->data['reason'])->toBe('last_super_admin')
        ->and(json_encode($entry->data))->not->toContain('only-admin-portal');
});

it('refuses a second scheduled erasure and says so', function () {
    Queue::fake();
    User::create(['name' => 'Twice', 'email' => 'twice-portal@example.com', 'password' => 'secret']);

    $this->post(portalLinkFor('twice-portal@example.com', 'erasure'))->assertOk();

    // Un second lien (jeton distinct) : le verrou de lien unique ne joue pas, c'est le pipeline qui refuse.
    $this->post(portalLinkFor('Twice-Portal@example.com', 'erasure'))
        ->assertStatus(409)
        ->assertSee(__('baobab::privacy.portal.refused_already_scheduled'));

    expect(PrivacyRequest::query()->count())->toBe(1);
});

it('shows the cancel page on GET without cancelling, and cancels only on POST', function () {
    Queue::fake();
    User::create(['name' => 'Cancelling', 'email' => 'cancelling-portal@example.com', 'password' => 'secret']);
    $this->post(portalLinkFor('cancelling-portal@example.com', 'erasure'));
    $request = PrivacyRequest::query()->firstOrFail();
    $url = URL::signedRoute('baobab.privacy.portal.cancel', ['privacyRequest' => $request->uuid]);

    $this->get($url)->assertOk()->assertSee(__('baobab::privacy.portal.cancel_submit'));
    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::Scheduled);

    $this->post($url)->assertOk()->assertSee(__('baobab::privacy.portal.cancelled'));
    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::Cancelled);

    $this->post($url)->assertStatus(410)->assertSee(__('baobab::privacy.portal.not_cancellable'));
    $this->get($url)->assertStatus(410);
});

it('refuses an unsigned cancel link and any request that is not a portal erasure', function () {
    Queue::fake();
    User::create(['name' => 'Guarded', 'email' => 'guarded-portal@example.com', 'password' => 'secret']);
    $erasure = app(CreatePrivacyRequest::class)(Subject::forEmail('guarded-portal@example.com'), null, 'portal', PrivacyRequestType::Erasure);
    $adminErasure = app(CreatePrivacyRequest::class)(Subject::forEmail('admin-side@example.com'), null, 'admin', PrivacyRequestType::Erasure);
    $export = app(CreatePrivacyRequest::class)(Subject::forEmail('guarded-portal@example.com'), null, 'portal');

    $this->get(route('baobab.privacy.portal.cancel', ['privacyRequest' => $erasure->uuid]))->assertForbidden();
    $this->post(route('baobab.privacy.portal.cancel.confirm', ['privacyRequest' => $erasure->uuid]))->assertForbidden();

    $this->get(URL::signedRoute('baobab.privacy.portal.cancel', ['privacyRequest' => $adminErasure->uuid]))->assertNotFound();
    $this->get(URL::signedRoute('baobab.privacy.portal.cancel', ['privacyRequest' => $export->uuid]))->assertNotFound();

    expect($erasure->refresh()->status)->toBe(PrivacyRequestStatus::Scheduled);
});

it('warns a portal requester when the export finds no data, and only then', function () {
    Queue::fake();

    $portal = app(CreatePrivacyRequest::class)(Subject::forEmail('nothing-portal@example.com'), null, 'portal');
    app(ExecutePersonalDataExport::class)($portal);

    expect($portal->refresh()->status)->toBe(PrivacyRequestStatus::Failed)
        ->and(portalMailTo('core.privacy_export_empty', 'nothing-portal@example.com')->html)->not->toContain('signature=');

    $admin = app(CreatePrivacyRequest::class)(Subject::forEmail('nothing-admin@example.com'), null, 'admin');
    app(ExecutePersonalDataExport::class)($admin);

    Queue::assertNotPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->to === 'nothing-admin@example.com');
});

it('warns a portal requester of a technical export failure without giving the cause', function () {
    Queue::fake();
    config()->set('baobab.exports.disk', 'a-disk-that-does-not-exist');
    User::create(['name' => 'Broken', 'email' => 'broken-portal@example.com', 'password' => 'secret']);

    $portal = app(CreatePrivacyRequest::class)(Subject::forEmail('broken-portal@example.com'), null, 'portal');
    app(ExecutePersonalDataExport::class)($portal);

    expect($portal->refresh()->status)->toBe(PrivacyRequestStatus::Failed)
        ->and(portalMailTo('core.privacy_export_failed', 'broken-portal@example.com')->html)->not->toContain('a-disk-that-does-not-exist');

    $admin = app(CreatePrivacyRequest::class)(Subject::forEmail('broken-portal@example.com'), null, 'admin');
    app(ExecutePersonalDataExport::class)($admin);

    Queue::assertPushed(SendQueuedMail::class, 1);
});
