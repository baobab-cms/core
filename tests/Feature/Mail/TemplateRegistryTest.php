<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\TemplateRegistry;

it('resolves the core.test template from config', function () {
    $template = app(TemplateRegistry::class)->find('core.test');

    expect($template->key)->toBe('core.test')
        ->and($template->subject)->toContain('test')
        ->and($template->body)->toContain('{{ sent_at }}');
});

it('resolves a template declared by an active module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/mailer');
    app(ActivateModule::class)('acme/mailer');

    $template = app(TemplateRegistry::class)->find('acme.mailer.welcome');

    expect($template->key)->toBe('acme.mailer.welcome')
        ->and($template->subject)->toBe('Bienvenue {{ user.name }} !');
});

it('does not resolve a template declared by an inactive module', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/mailer'); // installé, pas activé

    app(TemplateRegistry::class)->find('acme.mailer.welcome');
})->throws(MailTemplateNotFoundException::class);

it('throws for an unknown template key', function () {
    app(TemplateRegistry::class)->find('core.does_not_exist');
})->throws(MailTemplateNotFoundException::class);
