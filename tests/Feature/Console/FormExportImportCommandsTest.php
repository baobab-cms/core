<?php

use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\Form;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::ensureDirectoryExists(sys_get_temp_dir().'/baobab-forms-tests');
});

afterEach(function () {
    File::deleteDirectory(sys_get_temp_dir().'/baobab-forms-tests');
});

it('exports a form to the given output path', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $path = sys_get_temp_dir().'/baobab-forms-tests/contact.json';

    $this->artisan('baobab:forms:export', ['slug' => 'contact', '--output' => $path])->assertSuccessful();

    expect(File::exists($path))->toBeTrue();

    $decoded = json_decode(File::get($path), true);
    expect($decoded['slug'])->toBe('contact');
});

it('fails when exporting an unknown slug', function () {
    $this->artisan('baobab:forms:export', ['slug' => 'ghost'])->assertFailed();
});

it('imports a form from a file, creating it', function () {
    $path = sys_get_temp_dir().'/baobab-forms-tests/contact.json';
    File::put($path, json_encode([
        'source' => 'admin', 'format_version' => 1, 'slug' => 'contact', 'title' => 'Contact',
        'fields' => [], 'settings' => [], 'store_submissions' => true, 'retention_days' => 365, 'retain_ip' => false,
    ]));

    $this->artisan('baobab:forms:import', ['fichier' => $path])->assertSuccessful();

    expect(Form::where('slug', 'contact')->exists())->toBeTrue();
});

it('fails cleanly when the file does not exist', function () {
    $this->artisan('baobab:forms:import', ['fichier' => sys_get_temp_dir().'/baobab-forms-tests/missing.json'])
        ->assertFailed();
});

it('fails cleanly on a malformed export, without a raw exception', function () {
    $path = sys_get_temp_dir().'/baobab-forms-tests/bad.json';
    File::put($path, 'not json');

    $this->artisan('baobab:forms:import', ['fichier' => $path])->assertFailed();
});
