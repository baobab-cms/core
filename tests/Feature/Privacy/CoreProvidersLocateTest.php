<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Privacy\Providers\AuditLogProvider;
use Baobab\Privacy\Providers\ContentAuthorshipProvider;
use Baobab\Privacy\Providers\FormSubmissionsProvider;
use Baobab\Privacy\Providers\MailLogProvider;
use Baobab\Privacy\Providers\MediaProvider;
use Baobab\Privacy\Providers\UsersProvider;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function privacyLocateUser(string $email): User
{
    return User::create(['name' => 'Locate User', 'email' => $email, 'password' => 'secret']);
}

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function (): void {
    File::deleteDirectory(generatedModulesPath());
});

it('locates a user by id or by e-mail, and only a real one', function () {
    $user = privacyLocateUser('locate-users@example.com');
    $provider = new UsersProvider;

    expect($provider->locate(Subject::forUser($user)))->toBeTrue()
        ->and($provider->locate(Subject::forEmail('LOCATE-users@example.com')))->toBeTrue()
        ->and($provider->locate(Subject::forEmail('nobody@example.com')))->toBeFalse();
});

it('locates authored content through the author_id of every content type', function () {
    $author = privacyLocateUser('locate-author@example.com');
    $stranger = privacyLocateUser('locate-stranger@example.com');

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('PrivacyAuthorshipEntry', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    $modelClass = $contentType->modelClass();
    $modelClass::create(['name' => 'Signé', 'author_id' => $author->id, 'status' => 'draft']);

    $provider = new ContentAuthorshipProvider;

    expect($provider->locate(Subject::forUser($author)))->toBeTrue()
        ->and($provider->locate(Subject::forEmail('locate-author@example.com')))->toBeTrue()
        ->and($provider->locate(Subject::forUser($stranger)))->toBeFalse()
        ->and($provider->locate(Subject::forEmail('no-account@example.com')))->toBeFalse();
});

it('counts trashed authored content too', function () {
    $author = privacyLocateUser('locate-trashed-author@example.com');

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('PrivacyTrashedEntry', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    $modelClass = $contentType->modelClass();
    $modelClass::create(['name' => 'Supprimé', 'author_id' => $author->id, 'status' => 'draft'])->delete();

    expect((new ContentAuthorshipProvider)->locate(Subject::forUser($author)))->toBeTrue();
});

it('locates uploaded media, trash included', function () {
    $uploader = privacyLocateUser('locate-uploader@example.com');
    $stranger = privacyLocateUser('locate-media-stranger@example.com');

    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $uploader);
    $media->delete();

    $provider = new MediaProvider;

    expect($provider->locate(Subject::forUser($uploader)))->toBeTrue()
        ->and($provider->locate(Subject::forUser($stranger)))->toBeFalse();
});

it('locates a recipient in the mail log by e-mail, case-insensitively', function () {
    MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'Recipient@Example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Sent,
    ]);

    $provider = new MailLogProvider;

    expect($provider->locate(Subject::forEmail('recipient@example.com')))->toBeTrue()
        ->and($provider->locate(Subject::forEmail('other@example.com')))->toBeFalse();
});

it('locates a user in the mail log through the e-mail of the account', function () {
    $user = privacyLocateUser('locate-mail-account@example.com');
    MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'locate-mail-account@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Sent,
    ]);

    expect((new MailLogProvider)->locate(Subject::forUser($user)))->toBeTrue();
});

it('locates a user as audit actor, impersonator or object', function () {
    $actor = privacyLocateUser('locate-audit-actor@example.com');
    $impersonator = privacyLocateUser('locate-audit-impersonator@example.com');
    $object = privacyLocateUser('locate-audit-object@example.com');
    $absent = privacyLocateUser('locate-audit-absent@example.com');

    AuditEntry::create(['action' => 'test.actor', 'actor_id' => $actor->id]);
    AuditEntry::create(['action' => 'test.impersonated', 'impersonator_id' => $impersonator->id]);
    AuditEntry::create(['action' => 'test.object', 'auditable_type' => $object->getMorphClass(), 'auditable_id' => $object->id]);

    $provider = new AuditLogProvider;

    expect($provider->locate(Subject::forUser($actor)))->toBeTrue()
        ->and($provider->locate(Subject::forUser($impersonator)))->toBeTrue()
        ->and($provider->locate(Subject::forUser($object)))->toBeTrue()
        ->and($provider->locate(Subject::forUser($absent)))->toBeFalse()
        ->and($provider->locate(Subject::forEmail('no-account@example.com')))->toBeFalse();
});

it('locates a form submission through the e-mail fields of its own snapshot', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'privacy-contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'contact_mail', 'type' => 'email'],
            ['key' => 'note', 'type' => 'text'],
        ],
    ]);

    FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => ['contact_mail' => 'Visitor@Example.com', 'note' => 'other@example.com'],
        'status' => 'new',
    ]);

    $provider = new FormSubmissionsProvider;

    expect($provider->locate(Subject::forEmail('visitor@example.com')))->toBeTrue()
        // « note » n'est pas un champ e-mail : sa valeur ne compte jamais.
        ->and($provider->locate(Subject::forEmail('other@example.com')))->toBeFalse();
});

it('locates a form submission for a user through the e-mail of the account', function () {
    $user = privacyLocateUser('locate-form-account@example.com');
    $form = app(SaveForm::class)(null, [
        'slug' => 'privacy-contact-account',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email']],
    ]);

    FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => ['email' => 'locate-form-account@example.com'],
        'status' => 'new',
    ]);

    expect((new FormSubmissionsProvider)->locate(Subject::forUser($user)))->toBeTrue();
});
