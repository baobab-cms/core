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
use Baobab\Privacy\Actions\ExportPersonalData;
use Baobab\Privacy\Contracts\PersonalDataProvider;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** Ouvre l'archive avec son mot de passe ; `null` si elle refuse de s'ouvrir. */
function openPrivacyArchive(string $path, ?string $password): ?ZipArchive
{
    $zip = new ZipArchive;

    if ($zip->open($path) !== true) {
        return null;
    }

    if ($password !== null) {
        $zip->setPassword($password);
    }

    return $zip;
}

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('exports the account, its roles and its audit trail in an encrypted archive', function () {
    $user = User::create(['name' => 'Export User', 'email' => 'export-user@example.com', 'password' => 'secret']);
    AuditEntry::create(['action' => 'test.actor', 'actor_id' => $user->id, 'data' => ['note' => 'a fait quelque chose']]);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));

    $zip = openPrivacyArchive($archive->path, $archive->password);
    $profile = json_decode((string) $zip->getFromName('core.users/data.json'), true);
    $audit = json_decode((string) $zip->getFromName('core.audit_log/data.json'), true);

    expect($archive->providers)->toContain('core.users', 'core.audit_log')
        ->and($profile['profile']['email'])->toBe('export-user@example.com')
        ->and($profile['profile']['two_factor_enabled'])->toBeFalse()
        ->and($audit['entries'][0]['action'])->toBe('test.actor')
        ->and($audit['entries'][0]['role'])->toBe('actor');

    @unlink($archive->path);
});

it('never puts the password hash or the 2FA secrets in the archive', function () {
    $user = User::create(['name' => 'Secret User', 'email' => 'export-secrets@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $zip = openPrivacyArchive($archive->path, $archive->password);
    $json = (string) $zip->getFromName('core.users/data.json');

    expect($json)->not->toContain('password')
        ->and($json)->not->toContain('two_factor_secret')
        ->and($json)->not->toContain('two_factor_recovery_codes')
        ->and($json)->not->toContain((string) $user->getAuthPassword());

    @unlink($archive->path);
});

it('is unreadable without the password', function () {
    $user = User::create(['name' => 'Locked User', 'email' => 'export-locked@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));

    expect(openPrivacyArchive($archive->path, null)->getFromName('core.users/data.json'))->toBeFalse()
        ->and(openPrivacyArchive($archive->path, 'wrong-password')->getFromName('core.users/data.json'))->toBeFalse()
        ->and(strlen($archive->password))->toBeGreaterThanOrEqual(24);

    @unlink($archive->path);
});

it('writes a readable index.html linking every provider section', function () {
    $user = User::create(['name' => 'Index User', 'email' => 'export-index@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $index = (string) openPrivacyArchive($archive->path, $archive->password)->getFromName('index.html');

    expect($index)->toContain('export-index@example.com')
        ->and($index)->toContain('core.users');

    @unlink($archive->path);
});

it('escapes stored values in the index.html', function () {
    $user = User::create(['name' => '<script>alert(1)</script>', 'email' => 'export-xss@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $index = (string) openPrivacyArchive($archive->path, $archive->password)->getFromName('index.html');

    expect($index)->not->toContain('<script>alert(1)</script>')
        ->and($index)->toContain('&lt;script&gt;');

    @unlink($archive->path);
});

it('embeds the uploaded media file with its metadata', function () {
    $user = User::create(['name' => 'Media User', 'email' => 'export-media@example.com', 'password' => 'secret']);
    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'photo.jpg', 'image/jpeg', null, true), $user);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $zip = openPrivacyArchive($archive->path, $archive->password);
    $data = json_decode((string) $zip->getFromName('core.media/data.json'), true);
    $name = $data['media'][0]['file'];

    expect($data['media'][0]['uuid'])->toBe($media->uuid)
        ->and($zip->getFromName("core.media/files/{$name}"))->not->toBeFalse();

    @unlink($archive->path);
});

it('exports the mail log of an e-mail subject who has no account', function () {
    MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'Guest@Example.com',
        'subject' => 'Bonjour',
        'status' => MailLogStatus::Sent,
    ]);

    $archive = app(ExportPersonalData::class)(Subject::forEmail('guest@example.com'));
    $data = json_decode((string) openPrivacyArchive($archive->path, $archive->password)->getFromName('core.mail_log/data.json'), true);

    expect($archive->providers)->toBe(['core.mail_log'])
        ->and($data['emails'][0]['subject'])->toBe('Bonjour');

    @unlink($archive->path);
});

it('exports form submissions with their attachments, without the internal storage path', function () {
    $form = app(SaveForm::class)(null, [
        'slug' => 'privacy-export-contact',
        'title' => 'Contact export',
        'fields' => [
            ['key' => 'email', 'type' => 'email'],
            ['key' => 'cv', 'type' => 'file'],
        ],
    ]);
    Storage::disk('local')->put('form-submissions/2026/09/abc.pdf', 'PDF-BYTES');

    FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => [
            'email' => 'visitor-export@example.com',
            'cv' => ['original_name' => 'cv.pdf', 'stored_path' => 'form-submissions/2026/09/abc.pdf', 'mime_type' => 'application/pdf', 'size' => 9],
        ],
        'status' => 'new',
    ]);
    FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => ['email' => 'someone-else@example.com'],
        'status' => 'new',
    ]);

    $archive = app(ExportPersonalData::class)(Subject::forEmail('visitor-export@example.com'));
    $zip = openPrivacyArchive($archive->path, $archive->password);
    $json = (string) $zip->getFromName('forms.submissions/data.json');
    $data = json_decode($json, true);

    expect($data['submissions'])->toHaveCount(1)
        ->and($data['submissions'][0]['form'])->toBe('Contact export')
        ->and($json)->not->toContain('stored_path')
        ->and($json)->not->toContain('someone-else@example.com')
        ->and($zip->getFromName('forms.submissions/files/'.$data['submissions'][0]['payload']['cv']['file']))->toBe('PDF-BYTES');

    @unlink($archive->path);
});

it('flags a concerned provider that cannot export instead of silently skipping it', function () {
    app(PrivacyRegistry::class)->register(new class implements PersonalDataProvider
    {
        public function key(): string
        {
            return 'test.declaration_only';
        }

        public function describe(): DataDeclaration
        {
            return new DataDeclaration('Décl.', 'n', 'p', 'b', 'r');
        }

        public function locate(Subject $subject): bool
        {
            return true;
        }
    });
    $user = User::create(['name' => 'Partial User', 'email' => 'export-partial@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $index = (string) openPrivacyArchive($archive->path, $archive->password)->getFromName('index.html');

    expect($archive->unsupported)->toBe(['test.declaration_only'])
        ->and($archive->providers)->not->toContain('test.declaration_only')
        ->and($index)->toContain('test.declaration_only');

    @unlink($archive->path);
});

it('refuses to build an archive when no provider holds anything for the subject', function () {
    app(ExportPersonalData::class)(Subject::forEmail('nobody-at-all@example.com'));
})->throws(NoPersonalDataException::class);

it('audits the export without copying the exported content', function () {
    $user = User::create(['name' => 'Audited User', 'email' => 'export-audited@example.com', 'password' => 'secret']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($user));
    $entry = AuditEntry::query()->where('action', 'privacy.exported')->firstOrFail();

    expect($entry->auditable_id)->toBe($user->id)
        ->and($entry->data['providers'])->toContain('core.users')
        ->and(json_encode($entry->data))->not->toContain('export-audited@example.com');

    @unlink($archive->path);
});

it('exports authored content per content type, trash included, and only the subject\'s own', function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $author = User::create(['name' => 'Author Export', 'email' => 'export-author@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'Other Export', 'email' => 'export-other-author@example.com', 'password' => 'secret']);

    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('PrivacyExportEntry', [
        'fields' => [['key' => 'name', 'type' => 'text']],
    ]));
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($contentType->module_id));
    $modelClass = $contentType->modelClass();
    $modelClass::create(['name' => 'Mon article', 'author_id' => $author->id, 'status' => 'draft']);
    $modelClass::create(['name' => 'Article d\'un autre', 'author_id' => $other->id, 'status' => 'draft']);

    $archive = app(ExportPersonalData::class)(Subject::forUser($author));
    $json = (string) openPrivacyArchive($archive->path, $archive->password)->getFromName('core.content_authorship/data.json');

    expect($json)->toContain('Mon article')
        ->and($json)->not->toContain('Article d\'un autre');

    @unlink($archive->path);
    File::deleteDirectory(generatedModulesPath());
});
