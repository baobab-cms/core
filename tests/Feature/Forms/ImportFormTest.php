<?php

use Baobab\Forms\Actions\ExportForm;
use Baobab\Forms\Actions\ImportForm;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Exceptions\InvalidFormExportException;
use Baobab\Forms\Models\Form;

it('creates a new form from an export it has never seen', function () {
    $json = json_encode([
        'source' => 'admin',
        'format_version' => 1,
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
        'settings' => [],
        'store_submissions' => true,
        'retention_days' => 365,
        'retain_ip' => false,
    ]);

    $form = app(ImportForm::class)($json);

    expect($form->exists)->toBeTrue()
        ->and($form->slug)->toBe('contact')
        ->and($form->version)->toBe(1);
});

it('round-trips a real export through import, producing an equivalent form', function () {
    $original = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'topic', 'type' => 'select', 'options' => ['choices' => ['support', 'sales']]]],
        'retention_days' => 30,
    ]);

    $json = app(ExportForm::class)($original);
    $original->delete();

    $imported = app(ImportForm::class)($json);

    expect($imported->slug)->toBe('contact')
        ->and($imported->retention_days)->toBe(30)
        ->and($imported->blueprint['fields'][0]['options'])->toBe(['choices' => ['support', 'sales']]);
});

it('updates an existing form when the slug is already known, instead of duplicating it', function () {
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $json = json_encode([
        'source' => 'admin', 'format_version' => 1, 'slug' => 'contact', 'title' => 'Nous contacter',
        'fields' => [], 'settings' => [], 'store_submissions' => true, 'retention_days' => 365, 'retain_ip' => false,
    ]);

    $reimported = app(ImportForm::class)($json);

    expect($reimported->id)->toBe($form->id)
        ->and($reimported->title)->toBe('Nous contacter')
        ->and($reimported->version)->toBe(2)
        ->and(Form::count())->toBe(1);
});

it('rejects malformed JSON', function () {
    expect(fn () => app(ImportForm::class)('not json'))->toThrow(InvalidFormExportException::class);
});

it('rejects a source other than admin', function () {
    $json = json_encode(['source' => 'module', 'format_version' => 1, 'slug' => 'contact']);

    expect(fn () => app(ImportForm::class)($json))->toThrow(InvalidFormExportException::class);
});

it('rejects an unsupported format_version', function () {
    $json = json_encode(['source' => 'admin', 'format_version' => 2, 'slug' => 'contact']);

    expect(fn () => app(ImportForm::class)($json))->toThrow(InvalidFormExportException::class);
});

it('rejects an export missing its slug', function () {
    $json = json_encode(['source' => 'admin', 'format_version' => 1]);

    expect(fn () => app(ImportForm::class)($json))->toThrow(InvalidFormExportException::class);
});
