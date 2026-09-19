<?php

use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->outputDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'baobab_privacy_cmd_'.uniqid();
});

afterEach(function (): void {
    File::deleteDirectory($this->outputDir);
});

it('writes the archive, then prints its password once', function () {
    $user = User::create(['name' => 'Cmd User', 'email' => 'cmd-user@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:export', ['subject' => (string) $user->id, '--output' => $this->outputDir])
        ->expectsOutputToContain('Mot de passe de l\'archive')
        ->assertSuccessful();

    expect(File::glob($this->outputDir.'/privacy-export-*.zip'))->toHaveCount(1);
});

it('accepts an e-mail address as the subject', function () {
    User::create(['name' => 'Cmd Mail', 'email' => 'cmd-mail@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:export', ['subject' => 'CMD-mail@example.com', '--output' => $this->outputDir])
        ->assertSuccessful();
});

it('fails on an unknown account id without writing anything', function () {
    $this->artisan('baobab:privacy:export', ['subject' => '999999', '--output' => $this->outputDir])
        ->expectsOutputToContain('Sujet introuvable')
        ->assertFailed();

    expect(File::exists($this->outputDir))->toBeFalse();
});

it('fails when no data is held for an e-mail', function () {
    $this->artisan('baobab:privacy:export', ['subject' => 'nobody-cmd@example.com', '--output' => $this->outputDir])
        ->expectsOutputToContain('Aucune donnée personnelle')
        ->assertFailed();
});
