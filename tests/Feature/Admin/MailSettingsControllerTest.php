<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Models\MailSetting;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Écran de réglages du transport e-mail (spec 13 §2.1, suivi n° 187).
 * SMTP seul pour cette passe — patron exact `ApiSettingsTest`.
 */
it('denies access to the mail settings screen without baobab.system.mail.configure', function () {
    $user = User::create(['name' => 'No access', 'email' => 'no-mail-access@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.mails.settings'))
        ->assertForbidden();
});

it('shows and persists updates on the mail settings screen for a user with baobab.system.mail.configure', function () {
    $user = User::create(['name' => 'Mail admin', 'email' => 'mail-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.mail.configure');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.mails.settings'))
        ->assertOk();

    $this->actingAs($user, 'baobab')
        ->put(route('admin.mails.settings.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer@example.com',
            'password' => 'super-secret',
            'scheme' => '',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Site',
        ])
        ->assertRedirect(route('admin.mails.settings'));

    $setting = MailSetting::current();

    expect($setting->mailer)->toBe('smtp')
        ->and($setting->credentials['host'])->toBe('smtp.example.com')
        ->and($setting->credentials['port'])->toBe(587)
        ->and($setting->credentials['username'])->toBe('mailer@example.com')
        ->and($setting->credentials['password'])->toBe('super-secret')
        ->and($setting->from_address)->toBe('hello@example.com')
        ->and($setting->from_name)->toBe('Example Site');
});

it('never redisplays the saved password, and keeps it when the field is resubmitted blank', function () {
    $user = User::create(['name' => 'Mail admin', 'email' => 'mail-admin-2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.mail.configure');

    MailSetting::current()->fill([
        'mailer' => 'smtp',
        'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'password' => 'original-secret'],
    ])->save();

    $this->actingAs($user, 'baobab')
        ->get(route('admin.mails.settings'))
        ->assertOk()
        ->assertDontSee('original-secret');

    $this->actingAs($user, 'baobab')
        ->put(route('admin.mails.settings.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'password' => '',
            'scheme' => '',
        ])
        ->assertRedirect();

    expect(MailSetting::current()->credentials['password'])->toBe('original-secret');
});

it('overwrites the password when a new one is actually submitted', function () {
    $user = User::create(['name' => 'Mail admin', 'email' => 'mail-admin-3@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.mail.configure');

    MailSetting::current()->fill([
        'mailer' => 'smtp',
        'credentials' => ['host' => 'smtp.example.com', 'port' => 587, 'password' => 'original-secret'],
    ])->save();

    $this->actingAs($user, 'baobab')
        ->put(route('admin.mails.settings.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'password' => 'new-secret',
            'scheme' => '',
        ])
        ->assertRedirect();

    expect(MailSetting::current()->credentials['password'])->toBe('new-secret');
});

it('sends a test e-mail through SendTestMail from the settings screen', function () {
    Queue::fake();

    $user = User::create(['name' => 'Mail admin', 'email' => 'mail-admin-4@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.mail.configure');

    $this->actingAs($user, 'baobab')
        ->post(route('admin.mails.settings.test'), ['recipient' => 'diagnostic@example.com'])
        ->assertRedirect(route('admin.mails.settings'));

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'diagnostic@example.com' && $job->templateKey === 'core.test');
});
