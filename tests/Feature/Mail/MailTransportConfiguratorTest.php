<?php

use Baobab\Mail\Models\MailSetting;
use Baobab\Mail\Support\MailTransportConfigurator;

/**
 * Surcharge de config('mail.*') depuis le réglage persisté (spec 13 §2.1,
 * suivi n° 187). Testée directement plutôt qu'au travers d'un cycle de
 * boot complet : l'ordre migrations/providers de Testbench ne reproduit
 * pas fidèlement une requête réelle.
 */
it('leaves config(mail.*) untouched when no setting has been saved', function () {
    $defaultHost = config('mail.mailers.smtp.host');

    app(MailTransportConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($defaultHost);
});

it('leaves config(mail.*) untouched when the host is empty, even with a row present', function () {
    $defaultHost = config('mail.mailers.smtp.host');

    MailSetting::current()->fill(['mailer' => 'smtp', 'credentials' => []])->save();

    app(MailTransportConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($defaultHost);
});

it('overrides mail.mailers.smtp.* and mail.from.* once a host is configured', function () {
    MailSetting::current()->fill([
        'mailer' => 'smtp',
        'from_address' => 'hello@example.com',
        'from_name' => 'Example Site',
        'credentials' => [
            'host' => 'smtp.example.com',
            'port' => 2525,
            'username' => 'mailer@example.com',
            'password' => 'super-secret',
            'scheme' => 'smtps',
        ],
    ])->save();

    app(MailTransportConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer@example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('super-secret')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.address'))->toBe('hello@example.com')
        ->and(config('mail.from.name'))->toBe('Example Site');
});

it('never overrides the mailer when a non-smtp value is stored (SMTP-only for this pass)', function () {
    $defaultHost = config('mail.mailers.smtp.host');

    MailSetting::current()->fill([
        'mailer' => 'ses',
        'credentials' => ['host' => 'ignored.example.com'],
    ])->save();

    app(MailTransportConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($defaultHost);
});
