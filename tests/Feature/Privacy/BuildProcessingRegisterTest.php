<?php

use Baobab\Facades\Privacy;
use Baobab\Modules\Models\Module;
use Baobab\Privacy\Actions\BuildProcessingRegister;
use Baobab\Privacy\Contracts\PersonalDataProvider;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;
use Illuminate\Support\Facades\File;

function privacyRegisterModule(string $name, string $type, string $status, bool $withMigration): Module
{
    $path = sys_get_temp_dir().'/baobab-test-privacy-register/'.str_replace('/', '-', $name);
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path.'/database/migrations');

    if ($withMigration) {
        File::put($path.'/database/migrations/2026_01_01_000001_create_things_table.php', '<?php');
    }

    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => $type,
        'version' => '1.0.0',
        'provider' => 'Acme\\Privacy\\Provider',
        'source' => 'local',
        'path' => $path,
        'manifest' => ['name' => $name],
        'status' => $status,
    ]);
}

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/baobab-test-privacy-register');
});

it('builds one declaration per provider with the current configuration', function () {
    config(['baobab.audit.retention_days' => 111]);

    $register = app(BuildProcessingRegister::class)();

    expect($register->declarations)->toHaveKeys(['core.users', 'core.audit_log', 'forms.submissions'])
        ->and($register->declarations['core.audit_log']->retention)->toContain('111');
});

it('aggregates external mail services into the recipients list, and lists none for a local driver (spec 16 §6)', function () {
    config(['mail.default' => 'log']);
    expect(app(BuildProcessingRegister::class)()->recipients)->toBe([]);

    config(['mail.default' => 'ses']);
    $register = app(BuildProcessingRegister::class)();

    // Trois fournisseurs déclarent le même service : une seule ligne de destinataire.
    expect($register->recipients)->toHaveCount(1)
        ->and($register->recipients[0])->toContain('ses')
        ->and($register->declarations['core.mail_log']->externalServices)->toBe($register->recipients);
});

it('flags an active module that creates tables without declaring a provider', function () {
    privacyRegisterModule('acme/undeclared', 'module', 'active', withMigration: true);

    expect(app(BuildProcessingRegister::class)()->undeclaredModules)->toBe(['acme/undeclared']);
});

it('does not flag a module once it registers a provider under its slug', function () {
    privacyRegisterModule('acme/declared', 'module', 'active', withMigration: true);

    Privacy::register(new class implements PersonalDataProvider
    {
        public function key(): string
        {
            return 'declared.records';
        }

        public function describe(): DataDeclaration
        {
            return new DataDeclaration('Déclaré', 'n', 'f', 'b', 'r');
        }

        public function locate(Subject $subject): bool
        {
            return false;
        }
    });

    expect(app(BuildProcessingRegister::class)()->undeclaredModules)->toBe([]);
});

it('ignores inactive modules, content types, themes and modules without migrations', function () {
    privacyRegisterModule('acme/inactive', 'module', 'inactive', withMigration: true);
    privacyRegisterModule('content-types/some-type', 'content-type', 'active', withMigration: true);
    privacyRegisterModule('acme/some-theme', 'theme', 'active', withMigration: true);
    privacyRegisterModule('acme/no-tables', 'module', 'active', withMigration: false);

    expect(app(BuildProcessingRegister::class)()->undeclaredModules)->toBe([]);
});
