<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Support\FormSpamGuard;
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

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('baobab.exports.disk', 'local');
});

/** Le lien d'un e-mail envoyé par le portail, tel que la personne le recevrait. */
function portalMailLink(string $template, string $to): string
{
    $link = null;

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($template, $to, &$link): bool {
        if ($mail->templateKey !== $template || $mail->to !== $to) {
            return false;
        }

        preg_match('/href="([^"]+)"/', $mail->html, $matches);
        $link = html_entity_decode($matches[1] ?? '');

        return true;
    });

    return (string) $link;
}

function portalVerifyUrl(string $email, string $type = 'export', int $minutes = 30): string
{
    $token = Crypt::encryptString(json_encode(['email' => $email, 'type' => $type]));

    return URL::temporarySignedRoute('baobab.privacy.portal.verify', now()->addMinutes($minutes), ['token' => $token]);
}

/** Une demande du portail dont l'archive est prête, comme après `ExecutePersonalDataExport`. */
function portalDeliveredRequest(string $email = 'portal-person@example.com'): PrivacyRequest
{
    User::create(['name' => 'Portal Person', 'email' => $email, 'password' => 'secret']);

    $request = app(CreatePrivacyRequest::class)(Subject::forEmail($email), null, 'portal');
    app(ExecutePersonalDataExport::class)($request);

    return $request->refresh();
}

it('serves the portal page, never indexed, never cached, with the anti-spam fields', function () {
    $response = $this->get(route('baobab.privacy.portal'));

    $response->assertOk()
        ->assertSee(__('baobab::privacy.portal.intro'))
        ->assertSee('name="_form_hp"', false)
        ->assertSee('name="_form_rt"', false)
        ->assertSee('noindex', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sends a verification link to any address, with nothing personal in the message or the URL', function () {
    Queue::fake();

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'Someone-Unknown@Example.com'])
        ->assertRedirect(route('baobab.privacy.portal'));

    $link = portalMailLink('core.privacy_verification', 'someone-unknown@example.com');

    expect($link)->toContain('/baobab/privacy/verify?')
        ->and($link)->toContain('signature=')
        ->and(strtolower(urldecode($link)))->not->toContain('someone-unknown');

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => ! str_contains(strtolower($mail->html.$mail->text), 'someone-unknown'));
});

it('answers identically for a known and an unknown address, and sends a mail to both', function () {
    Queue::fake();
    User::create(['name' => 'Known', 'email' => 'known-portal@example.com', 'password' => 'secret']);

    $known = $this->post(route('baobab.privacy.portal.request'), ['email' => 'known-portal@example.com']);
    $unknown = $this->post(route('baobab.privacy.portal.request'), ['email' => 'unknown-portal@example.com']);

    expect($known->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($known->headers->get('Location'))->toBe($unknown->headers->get('Location'));

    $this->get(route('baobab.privacy.portal'))->assertSee(__('baobab::privacy.portal.sent'));

    portalMailLink('core.privacy_verification', 'known-portal@example.com');
    portalMailLink('core.privacy_verification', 'unknown-portal@example.com');
});

it('gives a suspicious entry the same answer without sending anything', function () {
    Queue::fake();

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'bot@example.com', '_form_hp' => 'gotcha'])
        ->assertRedirect(route('baobab.privacy.portal'));

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'fast-bot@example.com', '_form_rt' => FormSpamGuard::renderToken()])
        ->assertRedirect(route('baobab.privacy.portal'));

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('rejects a malformed address without sending anything', function () {
    Queue::fake();

    $this->from(route('baobab.privacy.portal'))
        ->post(route('baobab.privacy.portal.request'), ['email' => 'not-an-address'])
        ->assertSessionHasErrors('email');

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('limits the entry per address and per IP', function () {
    Queue::fake();

    foreach (range(1, 3) as $i) {
        $this->post(route('baobab.privacy.portal.request'), ['email' => 'flooded@example.com'])->assertRedirect();
    }

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'flooded@example.com'])->assertStatus(429);

    // Une autre adresse passe encore, jusqu'à la limite par IP (5 par minute, 3 déjà consommées).
    $this->post(route('baobab.privacy.portal.request'), ['email' => 'other-1@example.com'])->assertRedirect();
    $this->post(route('baobab.privacy.portal.request'), ['email' => 'other-2@example.com'])->assertRedirect();
    $this->post(route('baobab.privacy.portal.request'), ['email' => 'other-3@example.com'])->assertStatus(429);
});

it('shows a confirmation page on the link and never acts on a plain GET', function () {
    Queue::fake();

    $this->get(portalVerifyUrl('nobody-get@example.com'))
        ->assertOk()
        ->assertSee(__('baobab::privacy.portal.confirm_submit'));

    expect(PrivacyRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('refuses a tampered or expired link with a readable page', function () {
    $url = portalVerifyUrl('tampered@example.com');

    $this->get($url.'x')->assertForbidden()->assertSee(__('baobab::privacy.portal.state_invalid'));

    $this->travel(31)->minutes();
    $this->get($url)->assertForbidden()->assertSee(__('baobab::privacy.portal.state_invalid'));
});

it('refuses a signed link whose token is not ours', function () {
    Queue::fake();
    $url = URL::temporarySignedRoute('baobab.privacy.portal.verify', now()->addMinutes(30), ['token' => 'garbage']);

    $this->post($url)->assertForbidden()->assertSee(__('baobab::privacy.portal.state_invalid'));

    expect(PrivacyRequest::query()->count())->toBe(0);
});

it('creates the export request only on confirmation, once', function () {
    Queue::fake();
    $url = portalVerifyUrl('confirmed-portal@example.com');

    $this->post($url)->assertOk()->assertSee(__('baobab::privacy.portal.confirmed_export'));

    $request = PrivacyRequest::query()->firstOrFail();

    expect($request->type)->toBe(PrivacyRequestType::Export)
        ->and($request->status)->toBe(PrivacyRequestStatus::Pending)
        ->and($request->origin)->toBe('portal')
        ->and($request->subject_email)->toBe('confirmed-portal@example.com');

    Queue::assertPushedOn('baobab-low', RunPersonalDataExportJob::class);

    $this->post($url)->assertStatus(410)->assertSee(__('baobab::privacy.portal.state_used'));

    expect(PrivacyRequest::query()->count())->toBe(1);
});

it('audits the portal steps by pseudonym, never by address', function () {
    Queue::fake();

    $this->post(route('baobab.privacy.portal.request'), ['email' => 'audited-portal@example.com']);
    $this->post(portalVerifyUrl('audited-portal@example.com'));

    $entries = AuditEntry::query()->whereIn('action', ['privacy.portal.requested', 'privacy.portal.confirmed'])->get();

    expect($entries->pluck('action')->all())->toEqualCanonicalizing(['privacy.portal.requested', 'privacy.portal.confirmed'])
        ->and(json_encode($entries->pluck('data')))->not->toContain('audited-portal');
});

it('points the export-ready mail of a portal request to the delivery page, and an admin one to the download', function () {
    Queue::fake();
    $portal = portalDeliveredRequest('mail-portal@example.com');
    $admin = app(CreatePrivacyRequest::class)(Subject::forEmail('mail-portal@example.com'));
    app(ExecutePersonalDataExport::class)($admin);

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.privacy_export_ready'
        && str_contains($mail->html, "/baobab/privacy/delivery/{$portal->uuid}?signature="));

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.privacy_export_ready'
        && str_contains($mail->html, "/baobab/privacy/exports/{$admin->uuid}?signature="));
});

it('delivers the archive link on the page but reveals the password only on POST, once', function () {
    Queue::fake();
    $request = portalDeliveredRequest();
    $password = (string) $request->password;
    $url = URL::signedRoute('baobab.privacy.portal.delivery', ['privacyRequest' => $request->uuid]);

    $this->get($url)
        ->assertOk()
        ->assertSee("/baobab/privacy/exports/{$request->uuid}?signature=", false)
        ->assertSee(__('baobab::privacy.portal.password_reveal'))
        ->assertDontSee($password);

    // Le GET n'a rien consommé : un analyseur de liens ne prive pas la personne de son mot de passe.
    expect($request->refresh()->hasPendingPassword())->toBeTrue();

    $this->post($url)->assertOk()->assertSee($password);

    $this->post($url)->assertOk()->assertDontSee($password)->assertSee(__('baobab::privacy.portal.password_gone'));
    $this->get($url)->assertOk()->assertDontSee($password);
});

it('refuses an unsigned or altered delivery link', function () {
    Queue::fake();
    $request = portalDeliveredRequest();

    $this->get(route('baobab.privacy.portal.delivery', ['privacyRequest' => $request->uuid]))->assertForbidden();
    $this->get(URL::signedRoute('baobab.privacy.portal.delivery', ['privacyRequest' => $request->uuid]).'x')->assertForbidden();
});

it('answers 404 for a delivery page that is not a portal export', function () {
    Queue::fake();
    User::create(['name' => 'Admin Origin', 'email' => 'admin-origin@example.com', 'password' => 'secret']);
    $request = app(CreatePrivacyRequest::class)(Subject::forEmail('admin-origin@example.com'));
    app(ExecutePersonalDataExport::class)($request);

    $this->get(URL::signedRoute('baobab.privacy.portal.delivery', ['privacyRequest' => $request->uuid]))->assertNotFound();
});

it('answers 410 once the archive has expired', function () {
    Queue::fake();
    $request = portalDeliveredRequest();
    $url = URL::signedRoute('baobab.privacy.portal.delivery', ['privacyRequest' => $request->uuid]);

    $request->update(['expires_at' => now()->subMinute()]);

    $this->get($url)->assertStatus(410)->assertSee(__('baobab::privacy.portal.gone'));
});
