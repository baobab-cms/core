<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Auth\Actions\SetUserPassword;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Actions\DeactivateUser;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Exceptions\UserNotFoundException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Commande de secours du mot de passe (spec 05 §6.1, suivi n° 362) : change le
 * mot de passe d'un utilisateur existant sans e-mail, sans hiérarchie, sans
 * jamais l'accepter en argument.
 */
function rescuePasswordUser(string $email, string $role = 'author'): User
{
    $user = User::create(['name' => 'Rescue '.$email, 'email' => $email, 'password' => 'old-password-value']);
    $user->assignRole(Role::findByName($role, 'baobab'));

    return $user;
}

it('never takes the password as an argument or an option', function () {
    $definition = Artisan::all()['baobab:user:password']->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['email'])
        ->and($definition->hasOption('password'))->toBeFalse()
        ->and($definition->hasOption('password-env'))->toBeTrue()
        ->and($definition->hasOption('generate'))->toBeTrue();
});

it('changes the password from an environment variable, with the effects of any password change', function () {
    Notification::fake();
    $user = rescuePasswordUser('rescue-env@example.com');
    $user->forceFill([
        'remember_token' => 'token-before-rescue',
        'two_factor_secret' => 'SECRET',
        'two_factor_recovery_codes' => ['a', 'b'],
        'two_factor_confirmed_at' => now(),
    ])->save();
    DB::table('sessions')->insert(['id' => 'rescue-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    app(CreateApiToken::class)($user, 'rescue-token', []);
    putenv('BAOBAB_TEST_RESCUE_PASSWORD=A-Brand-New-Passw0rd!');

    $this->artisan('baobab:user:password', ['email' => $user->email, '--password-env' => 'BAOBAB_TEST_RESCUE_PASSWORD'])
        ->assertExitCode(0);

    $fresh = $user->fresh();

    expect(Hash::check('A-Brand-New-Passw0rd!', (string) $fresh->password))->toBeTrue()
        ->and($fresh->remember_token)->not->toBe('token-before-rescue')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($fresh->hasTwoFactorEnabled())->toBeTrue()
        ->and($fresh->tokens()->count())->toBe(1);

    putenv('BAOBAB_TEST_RESCUE_PASSWORD');
});

it('audits the change with its console origin and warns the user', function () {
    Notification::fake();
    $user = rescuePasswordUser('rescue-audit@example.com');

    app(SetUserPassword::class)($user->email, 'A-Brand-New-Passw0rd!');

    $entry = AuditEntry::query()->where('action', 'user.password.changed')->where('auditable_id', $user->id)->firstOrFail();

    expect($entry->data)->toBe(['source' => 'console']);

    Notification::assertSentTo(
        $user,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($user)['key'] === 'core.security.password_changed',
    );
});

it('asks for the password with a confirmation', function () {
    Notification::fake();
    $user = rescuePasswordUser('rescue-prompt@example.com');

    $this->artisan('baobab:user:password', ['email' => $user->email])
        ->expectsQuestion('Nouveau mot de passe', 'Typed-Passw0rd-Value!')
        ->expectsQuestion('Confirmez le mot de passe', 'Typed-Passw0rd-Value!')
        ->assertExitCode(0);

    expect(Hash::check('Typed-Passw0rd-Value!', (string) $user->fresh()->password))->toBeTrue();
});

it('forges a password with --generate and shows it once', function () {
    Notification::fake();
    $user = rescuePasswordUser('rescue-generate@example.com');

    $exitCode = Artisan::call('baobab:user:password', ['email' => $user->email, '--generate' => true]);

    preg_match('/Mot de passe généré : (\S+)/', Artisan::output(), $matches);

    expect($exitCode)->toBe(0)
        ->and($matches[1] ?? null)->not->toBeNull()
        ->and(Hash::check($matches[1], (string) $user->fresh()->password))->toBeTrue();
});

it('forges a password by default when there is no terminal and no variable', function () {
    Notification::fake();
    $user = rescuePasswordUser('rescue-nointeraction@example.com');

    Artisan::call('baobab:user:password', ['email' => $user->email, '--no-interaction' => true]);

    preg_match('/Mot de passe généré : (\S+)/', Artisan::output(), $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and(Hash::check($matches[1], (string) $user->fresh()->password))->toBeTrue();
});

it('accepts an account whose invitation is pending: choosing a password accepts it', function () {
    Queue::fake();
    Notification::fake();
    $admin = rescuePasswordUser('rescue-inviter@example.com', 'admin');
    $invited = app(InviteUser::class)($admin, 'Invited', 'rescue-invited@example.com', 'author');

    expect($invited->hasPendingInvitation())->toBeTrue();

    app(SetUserPassword::class)($invited->email, 'Chosen-Passw0rd-Value!');

    expect($invited->fresh()->hasPendingInvitation())->toBeFalse()
        ->and(Hash::check('Chosen-Passw0rd-Value!', (string) $invited->fresh()->password))->toBeTrue();
});

it('changes the password of any account, super-admin included, with no hierarchy check', function () {
    Notification::fake();
    $superAdmin = rescuePasswordUser('rescue-super@example.com', 'super-admin');

    app(SetUserPassword::class)($superAdmin->email, 'Super-Passw0rd-Rescued!');

    expect(Hash::check('Super-Passw0rd-Rescued!', (string) $superAdmin->fresh()->password))->toBeTrue();
});

it('refuses an unknown address and never creates an account', function () {
    $this->artisan('baobab:user:password', ['email' => 'nobody@example.com', '--generate' => true])
        ->assertExitCode(1);

    expect(User::query()->where('email', 'nobody@example.com')->exists())->toBeFalse()
        ->and(fn () => app(SetUserPassword::class)('nobody@example.com', null))->toThrow(UserNotFoundException::class);
});

it('refuses a deactivated account and leaves its password untouched', function () {
    Queue::fake();
    $admin = rescuePasswordUser('rescue-admin-deact@example.com', 'admin');
    $author = rescuePasswordUser('rescue-deactivated@example.com');
    app(DeactivateUser::class)($admin, $author);

    $this->artisan('baobab:user:password', ['email' => $author->email, '--generate' => true])
        ->assertExitCode(1);

    expect(Hash::check('old-password-value', (string) $author->fresh()->password))->toBeTrue()
        ->and(fn () => app(SetUserPassword::class)($author->email, null))->toThrow(InvalidAccountStateException::class);
});

it('refuses a weak password and leaves the current one in place', function () {
    $user = rescuePasswordUser('rescue-weak@example.com');
    putenv('BAOBAB_TEST_RESCUE_WEAK=short');

    $this->artisan('baobab:user:password', ['email' => $user->email, '--password-env' => 'BAOBAB_TEST_RESCUE_WEAK'])
        ->assertExitCode(1);

    expect(Hash::check('old-password-value', (string) $user->fresh()->password))->toBeTrue()
        ->and(fn () => app(SetUserPassword::class)($user->email, 'short'))->toThrow(ValidationException::class);

    putenv('BAOBAB_TEST_RESCUE_WEAK');
});

it('fails clearly when the named variable is not defined, instead of silently forging a password', function () {
    $user = rescuePasswordUser('rescue-novar@example.com');
    putenv('BAOBAB_TEST_RESCUE_MISSING');

    $this->artisan('baobab:user:password', ['email' => $user->email, '--password-env' => 'BAOBAB_TEST_RESCUE_MISSING'])
        ->assertExitCode(1);

    expect(Hash::check('old-password-value', (string) $user->fresh()->password))->toBeTrue();
});

it('refuses --generate together with --password-env', function () {
    $user = rescuePasswordUser('rescue-both@example.com');
    putenv('BAOBAB_TEST_RESCUE_BOTH=Some-Passw0rd-Value!');

    $this->artisan('baobab:user:password', ['email' => $user->email, '--password-env' => 'BAOBAB_TEST_RESCUE_BOTH', '--generate' => true])
        ->assertExitCode(1);

    expect(Hash::check('old-password-value', (string) $user->fresh()->password))->toBeTrue();

    putenv('BAOBAB_TEST_RESCUE_BOTH');
});
