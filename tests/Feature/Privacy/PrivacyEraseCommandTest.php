<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

it('asks for confirmation with --now and does nothing when declined', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-declined@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id, '--now' => true])
        ->expectsConfirmation('Effacer définitivement les données personnelles de ce sujet ? Cette action est irréversible.', 'no')
        ->expectsOutputToContain('Effacement annulé')
        ->assertFailed();

    expect(User::findOrFail($user->id)->email)->toBe('cmd-erase-declined@example.com');
});

it('erases after an explicit confirmation and prints the report', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-yes@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => 'cmd-erase-yes@example.com', '--now' => true])
        ->expectsConfirmation('Effacer définitivement les données personnelles de ce sujet ? Cette action est irréversible.', 'yes')
        ->expectsOutputToContain('Effacement terminé')
        ->expectsOutputToContain('core.users — anonymized')
        ->assertSuccessful();

    expect(User::findOrFail($user->id)->email)->toEndWith('@erased.invalid');
});

it('skips the confirmation with --now --force', function () {
    $user = User::create(['name' => 'Cmd Erase', 'email' => 'cmd-erase-force@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id, '--now' => true, '--force' => true])
        ->assertSuccessful();

    expect(User::findOrFail($user->id)->email)->toEndWith('@erased.invalid');
});

it('fails on an unknown account id', function () {
    $this->artisan('baobab:privacy:erase', ['subject' => '999999', '--now' => true, '--force' => true])
        ->expectsOutputToContain('Sujet introuvable')
        ->assertFailed();
});

it('fails when nothing is held for the e-mail', function () {
    $this->artisan('baobab:privacy:erase', ['subject' => 'nobody-cmd-erase@example.com', '--now' => true, '--force' => true])
        ->expectsOutputToContain('Aucune donnée personnelle')
        ->assertFailed();
});

it('reports the lockout instead of erasing the last super-admin', function () {
    $user = User::create(['name' => 'Cmd Solo', 'email' => 'cmd-erase-solo@example.com', 'password' => 'secret']);
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id, '--now' => true, '--force' => true])
        ->expectsOutputToContain('dernier super-administrateur')
        ->assertFailed();

    expect(User::findOrFail($user->id)->email)->toBe('cmd-erase-solo@example.com');
});

it('schedules an erasure by default, touches nothing and asks nothing', function () {
    $user = User::create(['name' => 'Cmd Sched', 'email' => 'cmd-erase-sched@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id])
        ->expectsOutputToContain('Effacement planifié le')
        ->assertSuccessful();

    $request = PrivacyRequest::query()->where('subject_user_id', $user->id)->firstOrFail();

    expect($request->type)->toBe(PrivacyRequestType::Erasure)
        ->and($request->status)->toBe(PrivacyRequestStatus::Scheduled)
        ->and($request->origin)->toBe('cli')
        ->and($request->scheduled_for->isFuture())->toBeTrue()
        ->and(User::findOrFail($user->id)->email)->toBe('cmd-erase-sched@example.com');
});

it('refuses to schedule a second erasure for the same subject', function () {
    $user = User::create(['name' => 'Cmd Twice', 'email' => 'cmd-erase-twice@example.com', 'password' => 'secret']);

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id])->assertSuccessful();
    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id])
        ->expectsOutputToContain('déjà planifié')
        ->assertFailed();

    expect(PrivacyRequest::query()->where('subject_user_id', $user->id)->count())->toBe(1);
});

it('refuses to schedule the erasure of the last super-admin', function () {
    $user = User::create(['name' => 'Cmd Solo Sched', 'email' => 'cmd-erase-solo-sched@example.com', 'password' => 'secret']);
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));

    $this->artisan('baobab:privacy:erase', ['subject' => (string) $user->id])
        ->expectsOutputToContain('dernier super-administrateur')
        ->assertFailed();

    expect(PrivacyRequest::query()->count())->toBe(0);
});
