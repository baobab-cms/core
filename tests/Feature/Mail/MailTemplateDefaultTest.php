<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Actions\RestoreMailTemplateDefault;
use Baobab\Mail\Actions\SaveMailTemplate;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\TemplateRegistry;

/**
 * Restauration du défaut (spec 13 §3.2) et détection de la dérive du défaut
 * livré par le code (le diff de la même section, dont l'affichage revient à
 * l'écran de la Pass A2).
 */
function declareMailDefault(string $subject, string $body): string
{
    $path = sys_get_temp_dir().'/baobab-test-mail-default-'.getmypid().'.json';

    file_put_contents($path, json_encode(['subject' => $subject, 'body' => $body]));

    config(['baobab.mail.templates' => [[
        'key' => 'core.invoice_ready',
        'description' => 'Fixture de test.',
        'variables' => ['customer.name' => 'Nom du client'],
        'defaults' => $path,
    ]]]);

    return 'core.invoice_ready';
}

it('restores the code default by removing the customisation', function () {
    $key = declareMailDefault('Sujet par défaut', 'Corps par défaut');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien', 'body' => 'Le mien aussi']);
    app(RestoreMailTemplateDefault::class)($key);

    expect(MailTemplateOverride::query()->count())->toBe(0)
        ->and(app(TemplateRegistry::class)->find($key)->subject)->toBe('Sujet par défaut')
        ->and(app(TemplateRegistry::class)->find($key)->customised)->toBeFalse()
        ->and(AuditEntry::where('action', 'mail.template_restored')->exists())->toBeTrue();
});

it('stays silent when restoring a template that was never customised', function () {
    $key = declareMailDefault('Sujet', 'Corps');

    app(RestoreMailTemplateDefault::class)($key);

    expect(AuditEntry::where('action', 'mail.template_restored')->exists())->toBeFalse();
});

it('refuses to restore a template no code declares', function () {
    expect(fn () => app(RestoreMailTemplateDefault::class)('ghost.template'))
        ->toThrow(MailTemplateNotFoundException::class);
});

it('reports no drift while the code default has not moved', function () {
    $key = declareMailDefault('Sujet', 'Corps');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien', 'body' => 'Le mien aussi']);

    expect(app(TemplateRegistry::class)->defaultDrift($key))->toBeNull();
});

it('reports no drift for a template that was never customised', function () {
    $key = declareMailDefault('Sujet', 'Corps');

    declareMailDefault('Sujet revu', 'Corps revu');

    expect(app(TemplateRegistry::class)->defaultDrift($key))->toBeNull();
});

it('reports the two versions of the default once the module updates it', function () {
    $key = declareMailDefault('Sujet v1', 'Corps v1');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien', 'body' => 'Le mien aussi']);

    declareMailDefault('Sujet v2', 'Corps v2');

    $drift = app(TemplateRegistry::class)->defaultDrift($key);

    expect($drift)->not->toBeNull()
        ->and($drift?->previousSubject)->toBe('Sujet v1')
        ->and($drift?->currentSubject)->toBe('Sujet v2')
        ->and($drift?->previousBody)->toBe('Corps v1')
        ->and($drift?->currentBody)->toBe('Corps v2')
        ->and($drift?->subjectChanged())->toBeTrue()
        ->and($drift?->bodyChanged())->toBeTrue();
});

it('never overwrites the admin version when the default drifts', function () {
    $key = declareMailDefault('Sujet v1', 'Corps v1');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien', 'body' => 'Le mien aussi']);

    declareMailDefault('Sujet v2', 'Corps v2');

    expect(app(TemplateRegistry::class)->find($key)->subject)->toBe('Le mien');
});

it('clears the drift once the admin saves against the new default', function () {
    $key = declareMailDefault('Sujet v1', 'Corps v1');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien', 'body' => 'Le mien aussi']);

    declareMailDefault('Sujet v2', 'Corps v2');

    app(SaveMailTemplate::class)($key, ['subject' => 'Le mien v2', 'body' => 'Le mien aussi v2']);

    expect(app(TemplateRegistry::class)->defaultDrift($key))->toBeNull();
});
