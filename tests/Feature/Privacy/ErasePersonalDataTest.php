<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Facades\Hook;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Privacy\Actions\ErasePersonalData;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\Providers\UsersProvider;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Baobab\Users\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function eraseUser(string $email): User
{
    return User::create(['name' => 'Erase Me', 'email' => $email, 'password' => 'secret']);
}

function eraseForm(string $slug): mixed
{
    return app(SaveForm::class)(null, [
        'slug' => $slug,
        'title' => 'Contact',
        'fields' => [
            ['key' => 'email', 'type' => 'email'],
            ['key' => 'cv', 'type' => 'file'],
        ],
    ]);
}

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('anonymizes the account in place into its own ghost', function () {
    $user = eraseUser('erase-account@example.com');
    $hash = Pseudonym::of('erase-account@example.com');
    $oldPasswordHash = $user->password;

    $result = app(ErasePersonalData::class)(Subject::forUser($user));
    $ghost = User::findOrFail($user->id);

    expect($ghost->name)->toBe("Utilisateur supprimé #{$hash}")
        ->and($ghost->email)->toBe("{$hash}@erased.invalid")
        ->and($ghost->password)->not->toBe($oldPasswordHash)
        ->and(Hash::check('secret', $ghost->password))->toBeFalse()
        ->and($ghost->remember_token)->toBeNull()
        ->and($result->reference)->toBe($hash)
        ->and($result->reports['core.users']->outcome)->toBe(EraseOutcome::Anonymized);
});

it('strips roles, tokens, sessions, preferences and reset tokens from the ghost', function () {
    $user = eraseUser('erase-strip@example.com');
    app(AssignRole::class)($user, Role::findByName('admin', 'baobab'));
    $user->createToken('cli');
    DB::table('sessions')->insert(['id' => 'sess-erase', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    if (! Schema::hasTable('password_reset_tokens')) {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
    DB::table('password_reset_tokens')->insert(['email' => 'erase-strip@example.com', 'token' => 'x']);
    NotificationPreference::create(['user_id' => $user->id, 'key' => 'core.test', 'channel' => 'mail', 'enabled' => true]);

    app(ErasePersonalData::class)(Subject::forUser($user));

    expect(User::findOrFail($user->id)->getRoleNames()->all())->toBe([])
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', 'erase-strip@example.com')->count())->toBe(0)
        ->and(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('is no longer located once the account is anonymized', function () {
    $user = eraseUser('erase-idem@example.com');

    expect((new UsersProvider)->locate(Subject::forUser($user)))->toBeTrue();

    app(ErasePersonalData::class)(Subject::forUser($user));

    expect((new UsersProvider)->locate(Subject::forUser($user)))->toBeFalse();
});

it('records a consolidated report in the audit, referenced by the pseudonym and never by the e-mail', function () {
    $user = eraseUser('erase-report@example.com');

    $result = app(ErasePersonalData::class)(Subject::forUser($user));
    $entry = AuditEntry::query()->where('action', 'privacy.erased')->firstOrFail();

    expect($entry->data['reference'])->toBe($result->reference)
        ->and($entry->data['reports'])->toHaveKey('core.users')
        ->and($entry->data['reports']['core.users']['outcome'])->toBe('anonymized')
        ->and(json_encode($entry->data))->not->toContain('erase-report@example.com');
});

it('deletes the form submissions and their attachments of the subject only', function () {
    $form = eraseForm('erase-contact');
    Storage::disk('local')->put('form-submissions/2026/09/mine.pdf', 'MINE');
    Storage::disk('local')->put('form-submissions/2026/09/other.pdf', 'OTHER');

    FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1, 'blueprint_snapshot' => $form->blueprint['fields'], 'status' => 'new',
        'payload' => ['email' => 'erase-visitor@example.com', 'cv' => ['original_name' => 'cv.pdf', 'stored_path' => 'form-submissions/2026/09/mine.pdf']],
    ]);
    FormSubmission::create([
        'form_id' => $form->id, 'form_version' => 1, 'blueprint_snapshot' => $form->blueprint['fields'], 'status' => 'new',
        'payload' => ['email' => 'erase-other@example.com', 'cv' => ['original_name' => 'cv.pdf', 'stored_path' => 'form-submissions/2026/09/other.pdf']],
    ]);

    $result = app(ErasePersonalData::class)(Subject::forEmail('erase-visitor@example.com'));

    expect($result->reports['forms.submissions']->outcome)->toBe(EraseOutcome::Deleted)
        ->and($result->reports['forms.submissions']->count)->toBe(1)
        ->and(FormSubmission::query()->count())->toBe(1)
        ->and(Storage::disk('local')->exists('form-submissions/2026/09/mine.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('form-submissions/2026/09/other.pdf'))->toBeTrue();
});

it('hashes the mail log recipient and empties body and error, keeping the timeline', function () {
    MailLogEntry::create(['template_key' => 'core.test', 'recipient' => 'Erase-Mail@Example.com', 'subject' => 'Bonjour', 'status' => MailLogStatus::Sent, 'body' => '<p>secret</p>', 'error' => 'boom']);
    MailLogEntry::create(['template_key' => 'core.test', 'recipient' => 'keep@example.com', 'subject' => 'Autre', 'status' => MailLogStatus::Sent, 'body' => '<p>keep</p>']);

    $result = app(ErasePersonalData::class)(Subject::forEmail('erase-mail@example.com'));
    $erased = MailLogEntry::query()->where('subject', 'Bonjour')->firstOrFail();

    expect($result->reports['core.mail_log']->outcome)->toBe(EraseOutcome::Anonymized)
        ->and($erased->recipient)->toBe(Pseudonym::address('erase-mail@example.com'))
        ->and($erased->body)->toBeNull()
        ->and($erased->error)->toBeNull()
        ->and($erased->template_key)->toBe('core.test')
        ->and(MailLogEntry::query()->where('recipient', 'keep@example.com')->firstOrFail()->body)->toBe('<p>keep</p>');
});

it('erases an e-mail subject who has no account without touching any user', function () {
    $bystander = eraseUser('erase-bystander@example.com');
    MailLogEntry::create(['template_key' => 'core.test', 'recipient' => 'no-account@example.com', 'subject' => 'Hello', 'status' => MailLogStatus::Sent]);

    $result = app(ErasePersonalData::class)(Subject::forEmail('no-account@example.com'));

    expect($result->reports)->not->toHaveKey('core.users')
        ->and(User::findOrFail($bystander->id)->email)->toBe('erase-bystander@example.com');
});

it('pseudonymizes the audit trail instead of deleting it', function () {
    $user = eraseUser('erase-audit@example.com');
    $admin = eraseUser('erase-audit-admin@example.com');

    $own = AuditEntry::create(['action' => 'test.own', 'actor_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'UA-mine', 'data' => ['note' => 'garde', 'email' => 'erase-audit@example.com', 'nested' => ['name' => 'Erase Me', 'kept' => 1]]]);
    $about = AuditEntry::create(['action' => 'test.about', 'actor_id' => $admin->id, 'auditable_type' => $user->getMorphClass(), 'auditable_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'UA-admin', 'data' => ['email' => 'erase-audit@example.com']]);
    $unrelated = AuditEntry::create(['action' => 'test.unrelated', 'actor_id' => $admin->id, 'ip_address' => '10.0.0.3', 'data' => ['email' => 'admin-own@example.com']]);

    $result = app(ErasePersonalData::class)(Subject::forUser($user));

    $own->refresh();
    $about->refresh();
    $unrelated->refresh();

    expect($result->reports['core.audit_log']->outcome)->toBe(EraseOutcome::Anonymized)
        ->and($own->ip_address)->toBeNull()
        ->and($own->user_agent)->toBeNull()
        ->and($own->data)->toBe(['note' => 'garde', 'nested' => ['kept' => 1]])
        ->and($own->actor_id)->toBe($user->id)
        // Le sujet n'est ici que l'objet : l'IP est celle de l'administrateur, elle reste.
        ->and($about->ip_address)->toBe('10.0.0.2')
        ->and($about->data)->toBe([])
        ->and($unrelated->ip_address)->toBe('10.0.0.3')
        ->and($unrelated->data)->toBe(['email' => 'admin-own@example.com']);
});

it('lets a module extend the personal keys scrubbed from the audit data', function () {
    Hook::modify('baobab.privacy.audit_personal_keys', fn (array $keys): array => [...$keys, 'phone']);
    $user = eraseUser('erase-keys@example.com');
    $entry = AuditEntry::create(['action' => 'test.keys', 'actor_id' => $user->id, 'data' => ['phone' => '0102030405', 'kept' => 'oui']]);

    app(ErasePersonalData::class)(Subject::forUser($user));

    expect($entry->refresh()->data)->toBe(['kept' => 'oui']);
});

it('keeps authored content and reports why', function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $author = eraseUser('erase-author@example.com');
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('PrivacyErasureEntry', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    $modelClass = $contentType->modelClass();
    $entry = $modelClass::create(['name' => 'Mon article', 'author_id' => $author->id, 'status' => 'draft']);

    $result = app(ErasePersonalData::class)(Subject::forUser($author));

    expect($result->reports['core.content_authorship']->outcome)->toBe(EraseOutcome::Retained)
        ->and($result->reports['core.content_authorship']->count)->toBe(1)
        ->and($result->reports['core.content_authorship']->note)->not->toBe('')
        ->and($modelClass::query()->find($entry->id)->author_id)->toBe($author->id)
        ->and(User::findOrFail($author->id)->name)->toStartWith('Utilisateur supprimé #');

    File::deleteDirectory(generatedModulesPath());
});

it('refuses to erase the last super-admin and changes nothing', function () {
    $user = eraseUser('erase-solo-admin@example.com');
    app(AssignRole::class)($user, Role::findByName('super-admin', 'baobab'));
    MailLogEntry::create(['template_key' => 'core.test', 'recipient' => 'erase-solo-admin@example.com', 'subject' => 'Hi', 'status' => MailLogStatus::Sent]);

    expect(fn () => app(ErasePersonalData::class)(Subject::forUser($user)))->toThrow(AdminLockoutException::class);

    expect(User::findOrFail($user->id)->email)->toBe('erase-solo-admin@example.com')
        ->and(MailLogEntry::query()->firstOrFail()->recipient)->toBe('erase-solo-admin@example.com');
});

it('erases a super-admin when another one remains', function () {
    $role = Role::findByName('super-admin', 'baobab');
    $first = eraseUser('erase-admin-1@example.com');
    $second = eraseUser('erase-admin-2@example.com');
    app(AssignRole::class)($first, $role);
    app(AssignRole::class)($second, $role);

    app(ErasePersonalData::class)(Subject::forUser($first));

    expect(User::findOrFail($first->id)->hasRole('super-admin', 'baobab'))->toBeFalse()
        ->and(User::findOrFail($second->id)->hasRole('super-admin', 'baobab'))->toBeTrue();
});

it('refuses an unknown account id and a subject holding nothing', function () {
    expect(fn () => app(ErasePersonalData::class)(new Subject(999999)))->toThrow(NoPersonalDataException::class)
        ->and(fn () => app(ErasePersonalData::class)(Subject::forEmail('nobody-erase@example.com')))->toThrow(NoPersonalDataException::class);
});

it('rolls the database back when a provider fails midway', function () {
    $user = eraseUser('erase-rollback@example.com');
    MailLogEntry::create(['template_key' => 'core.test', 'recipient' => 'erase-rollback@example.com', 'subject' => 'Hi', 'status' => MailLogStatus::Sent]);
    Hook::modify('baobab.privacy.audit_personal_keys', function (): never {
        throw new RuntimeException('provider failure');
    });
    AuditEntry::create(['action' => 'test.rollback', 'actor_id' => $user->id, 'data' => ['a' => 1]]);

    expect(fn () => app(ErasePersonalData::class)(Subject::forUser($user)))->toThrow(RuntimeException::class);

    expect(User::findOrFail($user->id)->email)->toBe('erase-rollback@example.com')
        ->and(MailLogEntry::query()->firstOrFail()->recipient)->toBe('erase-rollback@example.com');
});

it('keeps the audit log append-only at the model level, but not against retention purge', function () {
    $entry = AuditEntry::create(['action' => 'test.immutable']);

    expect(fn () => $entry->update(['action' => 'test.changed']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);

    AuditEntry::query()->where('action', 'test.immutable')->delete();

    expect(AuditEntry::query()->where('action', 'test.immutable')->count())->toBe(0);
});
