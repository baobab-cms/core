<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

it('asks for confirmation and does nothing when declined', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-declined@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id])
        ->expectsConfirmation('Effacer définitivement les données personnelles de ce sujet ? Cette action est irréversible.', 'no')
        ->expectsOutputToContain('Effacement annulé')
        ->assertFailed();

    expect(User::findOrFail($user->id)->email)->toBe('cmd-erase-declined@example.com');
});

it('erases after an explicit confirmation and prints the report', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-yes@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => 'cmd-erase-yes@example.com'])
        ->expectsConfirmation('Effacer définitivement les données personnelles de ce sujet ? Cette action est irréversible.', 'yes')
        ->expectsOutputToContain('Effacement terminé')
        ->expectsOutputToContain('core.users — anonymized')
        ->assertSuccessful();

    expect(User::findOrFail($user->id)->email)->toEndWith('@erased.invalid');
});

it('skips the confirmation with --force', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-force@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id, '--force' => true])
        ->assertSuccessful();

    expect(User::findOrFail($user->id)->email)->toEndWith('@erased.invalid');
});

it('fails on an unknown account id', function () {
    $this->artisan('baobab:privacy:erase', ['subject' => '999999', '--force' => true])
        ->expectsOutputToContain('Sujet introuvable')
        ->assertFailed();
});

it('fails when nothing is held for the e-mail', function () {
    $this->artisan('baobab:privacy:erase', ['subject' => 'nobody-cmd-erase@example.com', '--force' => true])
        ->expectsOutputToContain('Aucune donnée personnelle')
        ->assertFailed();
});

it('reports the lockout instead of erasing the last super-admin', function () {
    $user = User::create(['name' => 'Cmd Solo', 'email' => 'cmd-erase-solo@example.com', 'password' => 'secret']);
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id, '--force' => true])
        ->expectsOutputToContain('dernier super-administrateur')
        ->assertFailed();

    expect(User::findOrFail($user->id)->email)->toBe('cmd-erase-solo@example.com');
});
