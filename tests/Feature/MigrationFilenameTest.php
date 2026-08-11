<?php

use Baobab\ContentTypes\Generator\MigrationFilename;
use Illuminate\Support\Facades\File;

function filenameFixtureDir(): string
{
    return sys_get_temp_dir().'/baobab-test-migration-filename';
}

beforeEach(function () {
    File::deleteDirectory(filenameFixtureDir());
});

afterEach(function () {
    File::deleteDirectory(filenameFixtureDir());
});

it('invents a timestamped name when nothing exists yet', function () {
    $name = MigrationFilename::create(filenameFixtureDir(), 'members');

    expect($name)->toStartWith('database/migrations/')
        ->and($name)->toEndWith('_create_members_table.php');
});

/**
 * Le défaut relevé le 10 août 2026 : trois régénérations laissaient quatre
 * fichiers pour une seule table, et l'installation suivante rejouait un
 * `CREATE TABLE` sur une table déjà là.
 */
it('reuses the existing file rather than adding a second one for the same table', function () {
    $first = MigrationFilename::create(filenameFixtureDir(), 'members');

    File::ensureDirectoryExists(filenameFixtureDir().'/database/migrations');
    File::put(filenameFixtureDir().'/'.$first, '<?php // migration');

    expect(MigrationFilename::create(filenameFixtureDir(), 'members'))->toBe($first)
        ->and(MigrationFilename::create(filenameFixtureDir(), 'members'))->toBe($first);
});

it('keeps each table apart, and never reuses a name across tables', function () {
    File::ensureDirectoryExists(filenameFixtureDir().'/database/migrations');
    File::put(filenameFixtureDir().'/database/migrations/2026_01_01_000000_aaaaaa_create_members_table.php', '<?php');

    $cars = MigrationFilename::create(filenameFixtureDir(), 'cars');

    expect($cars)->toEndWith('_create_cars_table.php')
        ->and($cars)->not->toContain('members');
});

/**
 * `create_members_table` ne doit pas être confondu avec `create_team_members_table` :
 * le suffixe comparé commence par un souligné, donc `_create_members_table.php`
 * ne peut pas coïncider avec la fin de `..._create_team_members_table.php`.
 */
it('does not mistake a table whose name ends with another table name', function () {
    File::ensureDirectoryExists(filenameFixtureDir().'/database/migrations');
    File::put(filenameFixtureDir().'/database/migrations/2026_01_01_000000_aaaaaa_create_team_members_table.php', '<?php');

    expect(MigrationFilename::create(filenameFixtureDir(), 'members'))
        ->not->toContain('team_members');
});

/**
 * Cas des modules régénérés *avant* le correctif, qui portent déjà des
 * doublons : on retient le plus ancien, celui que la table `migrations`
 * connaît puisque c'est lui qui a été joué à la première installation.
 */
it('settles on the oldest file when duplicates are already there', function () {
    File::ensureDirectoryExists(filenameFixtureDir().'/database/migrations');

    foreach (['2026_08_10_140332_790e55', '2026_08_10_102109_da11e3', '2026_08_10_135823_7d850f'] as $prefix) {
        File::put(filenameFixtureDir()."/database/migrations/{$prefix}_create_members_table.php", '<?php');
    }

    expect(MigrationFilename::create(filenameFixtureDir(), 'members'))
        ->toBe('database/migrations/2026_08_10_102109_da11e3_create_members_table.php');
});
