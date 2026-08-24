<?php

use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Contracts\MailSampleProvider;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\MailTemplateValidator;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Mail\TemplateRegistry;
use Baobab\Modules\ModuleManifest;

final class WelcomeSample implements MailSampleProvider
{
    public function sample(): array
    {
        return ['sent_at' => '14 juillet 2026 à 10h00'];
    }
}

final class WelcomeResolver implements MailDataResolver
{
    public function resolve(MailLogEntry $entry): array
    {
        return [];
    }
}

final class NotASampleProvider {}

function coreTestTemplate(array $extra = []): void
{
    config(['baobab.mail.templates' => [
        [
            'key' => 'core.test',
            'description' => 'E-mail de test.',
            'variables' => ['sent_at' => 'Date/heure d\'envoi du test'],
            'defaults' => realpath(__DIR__.'/../../../resources/mails/core/test.json'),
        ] + $extra,
    ]]);
}

function manifestWith(string $attribute, string $class): ModuleManifest
{
    return ModuleManifest::fromJson(json_encode([
        'name' => 'acme/resendable',
        'title' => 'Resendable fixture',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Resendable\\Providers\\ResendableServiceProvider',
        'mails' => [[
            'key' => 'acme.resendable.welcome',
            'variables' => ['user.email' => 'E-mail de l\'utilisateur'],
            'defaults' => 'mails/welcome.json',
            $attribute => $class,
        ]],
    ], JSON_THROW_ON_ERROR));
}

it('reads resolver and sample from the declaration', function () {
    coreTestTemplate(['resolver' => WelcomeResolver::class, 'sample' => WelcomeSample::class]);

    $declaration = app(TemplateRegistry::class)->declaration('core.test');

    expect($declaration->resolver)->toBe(WelcomeResolver::class)
        ->and($declaration->sample)->toBe(WelcomeSample::class)
        ->and($declaration->isResendable())->toBeTrue();
});

it('is not resendable when no resolver is declared', function () {
    coreTestTemplate();

    expect(app(TemplateRegistry::class)->declaration('core.test')->isResendable())->toBeFalse();
});

it('uses the declared sample data for the preview', function () {
    coreTestTemplate(['sample' => WelcomeSample::class]);

    expect(app(TemplateRegistry::class)->declaration('core.test')->sampleValues())
        ->toBe(['sent_at' => '14 juillet 2026 à 10h00']);
});

/**
 * Le repli sur les libellés reste le comportement par défaut — c'est ce qui
 * évite d'exiger un `sample` de chaque template (suivi n° 190).
 */
it('falls back to variable labels when no sample is declared', function () {
    coreTestTemplate();

    expect(app(TemplateRegistry::class)->declaration('core.test')->sampleValues())
        ->toBe(['sent_at' => 'Date/heure d\'envoi du test']);
});

it('refuses a manifest whose resolver class does not exist', function () {
    app(MailTemplateValidator::class)->assertValid(
        manifestWith('resolver', 'Acme\\Nowhere\\MissingResolver'),
        fixtureModulesPath('local/acme-mailer'),
    );
})->throws(InvalidMailTemplateException::class, 'introuvable');

it('refuses a manifest whose sample class does not honour the contract', function () {
    app(MailTemplateValidator::class)->assertValid(
        manifestWith('sample', NotASampleProvider::class),
        fixtureModulesPath('local/acme-mailer'),
    );
})->throws(InvalidMailTemplateException::class, 'doit implémenter');
