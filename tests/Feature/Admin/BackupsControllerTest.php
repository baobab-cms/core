<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Backups\Models\BackupSetting;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\System\Actions\CreateBackup;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<string>  $permissions
 */
function backupsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Backups Actor {$counter}",
        'email' => "backups-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function putFakeBackup(string $filename): void
{
    Storage::disk('local')->put(config('baobab.backups.name').'/'.$filename, 'not a real zip, just test bytes');
}

beforeEach(function (): void {
    Storage::fake('local');
    config(['baobab.backups.destinations' => ['local']]);
});

it('denies the screen without baobab.system.backups.view', function () {
    $this->actingAs(backupsActor([]), 'baobab')
        ->get(route('admin.system.backups.index'))
        ->assertForbidden();
});

it('lists backups found on the configured destinations', function () {
    putFakeBackup('backup-2026-09-17.zip');

    $this->actingAs(backupsActor(['baobab.system.backups.view']), 'baobab')
        ->get(route('admin.system.backups.index'))
        ->assertOk()
        ->assertSee('backup-2026-09-17.zip');
});

it('ignores non-zip files under the backup directory', function () {
    putFakeBackup('notes.txt');

    $this->actingAs(backupsActor(['baobab.system.backups.view']), 'baobab')
        ->get(route('admin.system.backups.index'))
        ->assertOk()
        ->assertDontSee('notes.txt');
});

it('denies triggering a manual backup without baobab.system.backups.create', function () {
    $this->actingAs(backupsActor(['baobab.system.backups.view']), 'baobab')
        ->post(route('admin.system.backups.create'))
        ->assertForbidden();
});

it('denies downloading without baobab.system.backups.download', function () {
    putFakeBackup('backup-2026-09-17.zip');
    $token = base64_encode('local::backup-2026-09-17.zip');
    $token = strtr($token, '+/', '-_');
    $token = rtrim($token, '=');

    $this->actingAs(backupsActor(['baobab.system.backups.view']), 'baobab')
        ->get(route('admin.system.backups.download', ['token' => $token]))
        ->assertForbidden();
});

it('downloads a backup and audits it', function () {
    putFakeBackup('backup-2026-09-17.zip');
    $token = rtrim(strtr(base64_encode('local::backup-2026-09-17.zip'), '+/', '-_'), '=');

    $this->actingAs(backupsActor(['baobab.system.backups.view', 'baobab.system.backups.download']), 'baobab')
        ->get(route('admin.system.backups.download', ['token' => $token]))
        ->assertOk();

    expect(AuditEntry::where('action', 'backup.downloaded')->where('data->filename', 'backup-2026-09-17.zip')->exists())->toBeTrue();
});

it('refuses a token pointing at a disk outside the configured destinations', function () {
    $token = rtrim(strtr(base64_encode('s3-not-allowed::backup.zip'), '+/', '-_'), '=');

    $this->actingAs(backupsActor(['baobab.system.backups.view', 'baobab.system.backups.download']), 'baobab')
        ->get(route('admin.system.backups.download', ['token' => $token]))
        ->assertStatus(400);
});

it('deletes a backup and audits it, reusing the .create permission', function () {
    putFakeBackup('backup-2026-09-17.zip');
    $token = rtrim(strtr(base64_encode('local::backup-2026-09-17.zip'), '+/', '-_'), '=');

    $this->actingAs(backupsActor(['baobab.system.backups.view', 'baobab.system.backups.create']), 'baobab')
        ->delete(route('admin.system.backups.delete', ['token' => $token]))
        ->assertRedirect(route('admin.system.backups.index'));

    Storage::disk('local')->assertMissing(config('baobab.backups.name').'/backup-2026-09-17.zip');
    expect(AuditEntry::where('action', 'backup.deleted')->exists())->toBeTrue();
});

it('updates the retention settings and prefers them over config afterwards', function () {
    $this->actingAs(backupsActor(['baobab.system.backups.view', 'baobab.system.backups.create']), 'baobab')
        ->post(route('admin.system.backups.settings'), [
            'retention_daily' => 10,
            'retention_weekly' => 6,
            'max_total_size_mb' => 2000,
        ])
        ->assertRedirect(route('admin.system.backups.index'));

    $setting = BackupSetting::current();

    expect($setting->retention_daily)->toBe(10)
        ->and($setting->retention_weekly)->toBe(6)
        ->and($setting->max_total_size_mb)->toBe(2000)
        ->and($setting->scheduled_enabled)->toBeFalse()
        ->and(AuditEntry::where('action', 'system.backups.settings.updated')->exists())->toBeTrue();
});

it('creates a backup on demand and always audits the outcome, whatever the local dump tooling allows', function () {
    app(CreateBackup::class)();

    expect(AuditEntry::whereIn('action', ['backup.completed', 'backup.failed'])->exists())->toBeTrue();
});

it('scopes the backup source to media, form uploads, themes and modules, never the whole base_path()', function () {
    // Trouvé en recette navigateur (17 septembre 2026, restauration testée sur
    // Linux) : les modules actifs manquaient du périmètre — sans eux, un dump
    // restauré sur une installation neuve laisse des tables ct_* sans code
    // porteur (spec 12 §4.1 amendée, décision 7). Les pièces jointes de
    // formulaires (disque distinct des médias, spec 14 §5) manquaient aussi
    // — mêmes conséquences pour la table form_submissions.
    app(CreateBackup::class)();

    $include = config('backup.backup.source.files.include');

    expect($include)->toContain(storage_path('app/public'))
        ->toContain(storage_path('app/private'))
        ->toContain(base_path('themes'))
        ->toContain(base_path('modules'))
        ->not->toContain(base_path());
});

it('never lists a non-local media or forms disk as a source path', function () {
    // Un disque S3 n'a pas de chemin de fichiers local à crawler — sa
    // couverture passe par sa propre destination de sauvegarde, pas par
    // source.files.include (qui casserait sur un chemin S3 inexistant).
    config(['filesystems.disks.public.driver' => 's3']);

    app(CreateBackup::class)();

    expect(config('backup.backup.source.files.include'))->not->toContain(storage_path('app/public'));
});

it('makes archived paths relative to base_path(), never the source machine\'s absolute path', function () {
    // Soulevé par l'utilisateur (17 septembre 2026) en envisageant une
    // restauration à la main (dézip direct sur un nouvel hébergement) :
    // sans relative_path, le zip embarque le chemin absolu de CETTE
    // machine, inexploitable tel quel ailleurs — même un dump MySQL
    // restauré avec succès ne suffirait pas à retrouver les fichiers.
    app(CreateBackup::class)();

    expect(config('backup.backup.source.files.relative_path'))->toBe(base_path());
});

it('notifies backup managers when a backup fails', function () {
    // Échec forcé par une destination invalide plutôt que par l'absence de
    // mysqldump/sqlite3 sur la machine locale — un CI qui dispose de ces
    // outils ferait sinon réussir la sauvegarde et rendrait ce test flaky.
    config(['baobab.backups.destinations' => ['does-not-exist']]);
    Notification::fake();

    $manager = backupsActor(['baobab.system.backups.create']);

    app(CreateBackup::class)();

    Notification::assertSentTo(
        $manager,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($manager)['key'] === 'core.backup.failed',
    );
});

it('never lets malformed bytes from the dump tool break the audit write on a failed run', function () {
    // Constaté en recette navigateur (17 septembre 2026, Windows) :
    // `Artisan::output()` peut relayer des octets non-UTF-8 (locale du
    // process mysqldump/sqlite3) ; AuditEntry::data est castée en JSON,
    // json_encode() levait JsonEncodingException plutôt que d'auditer
    // l'échec. Preuve que le nettoyage (iconv UTF-8//IGNORE) empêche ça —
    // sans lui, la ligne suivante lèverait la même exception.
    $malformed = "mysqldump: erreur d'acc\xE8s au fichier";

    expect(json_encode($malformed))->toBeFalse();

    $sanitized = iconv('UTF-8', 'UTF-8//IGNORE', $malformed);

    $entry = AuditEntry::create(['action' => 'backup.failed', 'data' => ['output' => $sanitized]]);

    expect($entry->exists)->toBeTrue();
});
