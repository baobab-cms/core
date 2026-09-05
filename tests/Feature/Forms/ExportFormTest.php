<?php

use Baobab\Forms\Actions\ExportForm;
use Baobab\Forms\Actions\SaveForm;

it('exports a form as a self-contained JSON envelope (spec 14 §2.1)', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true, 'label' => 'E-mail']],
        'retention_days' => 90,
    ]);

    $json = app(ExportForm::class)($form);
    $decoded = json_decode($json, true);

    expect($decoded)->toBe([
        'source' => 'admin',
        'format_version' => 1,
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [[
            'key' => 'email',
            'type' => 'email',
            'label' => 'E-mail',
            'placeholder' => null,
            'help_text' => null,
            'required' => true,
            'options' => [],
        ]],
        'settings' => [],
        'store_submissions' => true,
        'retention_days' => 90,
        'retain_ip' => false,
    ]);
});
