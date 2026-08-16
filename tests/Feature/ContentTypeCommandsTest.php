<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function blueprintFixturePath(): string
{
    return sys_get_temp_dir().'/baobab-test-blueprint.json';
}

// ── content-type:build ──────────────────────────────────────────────────────────

it('content-type:build constructs a content type from a blueprint file', function () {
    File::put(blueprintFixturePath(), contentTypeBlueprintJson('ContentTypeCommandsEntry'));

    $exitCode = Artisan::call('content-type:build', ['path' => blueprintFixturePath()]);

    expect($exitCode)->toBe(0)
        ->and(ContentType::where('key', 'ContentTypeCommandsEntry')->exists())->toBeTrue()
        ->and(Schema::hasTable('ct_content_type_commands_entries'))->toBeTrue();

    File::delete(blueprintFixturePath());
});

it('content-type:build fails cleanly when the file does not exist', function () {
    $exitCode = Artisan::call('content-type:build', ['path' => sys_get_temp_dir().'/does-not-exist.json']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('introuvable');
});

it('content-type:build fails cleanly on an invalid blueprint', function () {
    File::put(blueprintFixturePath(), (string) json_encode(['key' => 'content_type_commands_entry']));

    $exitCode = Artisan::call('content-type:build', ['path' => blueprintFixturePath()]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Blueprint de Content Type invalide');

    File::delete(blueprintFixturePath());
});

// ── content-type:make ─────────────────────────────────────────────────────────

it('content-type:make builds a minimal content type with no fields or relations', function () {
    $this->artisan('content-type:make', ['key' => 'Widget'])
        ->expectsQuestion('Libellé singulier', 'Widget')
        ->expectsQuestion('Libellé pluriel', 'Widgets')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'no')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'yes')
        ->assertExitCode(0);

    expect(ContentType::where('key', 'Widget')->exists())->toBeTrue()
        ->and(Schema::hasTable('ct_widgets'))->toBeTrue();
});

it('content-type:make adds a real field column', function () {
    $this->artisan('content-type:make', ['key' => 'Gadget'])
        ->expectsQuestion('Libellé singulier', 'Gadget')
        ->expectsQuestion('Libellé pluriel', 'Gadgets')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'no')
        ->expectsConfirmation('Ajouter un champ ?', 'yes')
        ->expectsQuestion('Clé du champ (snake_case)', 'brand')
        ->expectsQuestion('Type de champ', 'text')
        ->expectsQuestion('Libellé affiché', 'Marque')
        ->expectsConfirmation('Obligatoire ?', 'yes')
        ->expectsQuestion('Options (JSON, vide si aucune)', '')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'yes')
        ->assertExitCode(0);

    // Le libellé diffère du repli humanisé (« Brand ») : il est donc écrit au
    // blueprint, et c'est lui que l'admin affichera.
    expect(Schema::hasColumn('ct_gadgets', 'brand'))->toBeTrue()
        ->and(ContentType::where('key', 'Gadget')->firstOrFail()->blueprint['fields'][0]['label'])->toBe('Marque');
});

it('content-type:make sets title_field for an addressable content type', function () {
    $this->artisan('content-type:make', ['key' => 'Article'])
        ->expectsQuestion('Libellé singulier', 'Article')
        ->expectsQuestion('Libellé pluriel', 'Articles')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'yes')
        ->expectsConfirmation('Ajouter un champ ?', 'yes')
        ->expectsQuestion('Clé du champ (snake_case)', 'title')
        ->expectsQuestion('Type de champ', 'text')
        ->expectsQuestion('Libellé affiché', 'Title')
        ->expectsConfirmation('Obligatoire ?', 'yes')
        ->expectsQuestion('Options (JSON, vide si aucune)', '')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsQuestion('Champ source du slug', 'title')
        ->expectsConfirmation('Désigner explicitement le body_field ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'yes')
        ->assertExitCode(0);

    $contentType = ContentType::where('key', 'Article')->firstOrFail();

    // Le libellé saisi vaut exactement le repli humanisé : il n'est pas écrit
    // au blueprint, et `FieldDisplay::label()` produira la même chaîne.
    expect($contentType->blueprint['title_field'])->toBe('title')
        ->and($contentType->blueprint['fields'][0])->not->toHaveKey('label')
        ->and($contentType->blueprint)->not->toHaveKey('body_field')
        ->and(Schema::hasColumn('ct_articles', 'slug'))->toBeTrue();
});

it('content-type:make adds a real relation to an already-built content type', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Manufacturer',
        'label' => ['singular' => 'Fabricant', 'plural' => 'Fabricants'],
    ]));

    $this->artisan('content-type:make', ['key' => 'Vehicle'])
        ->expectsQuestion('Libellé singulier', 'Vehicle')
        ->expectsQuestion('Libellé pluriel', 'Vehicles')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'no')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'yes')
        ->expectsQuestion('Clé de la relation', 'manufacturer')
        ->expectsQuestion('Type de relation', 'one_to_many')
        ->expectsQuestion('Cible', 'Manufacturer')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'yes')
        ->assertExitCode(0);

    expect(Schema::hasColumn('ct_vehicles', 'manufacturer_id'))->toBeTrue();
});

it('content-type:make aborts cleanly when the final confirmation is refused', function () {
    $this->artisan('content-type:make', ['key' => 'Abandoned'])
        ->expectsQuestion('Libellé singulier', 'Abandoned')
        ->expectsQuestion('Libellé pluriel', 'Abandoneds')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'no')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'no')
        ->assertExitCode(0);

    expect(ContentType::where('key', 'Abandoned')->exists())->toBeFalse();
});

it('content-type:make fails cleanly on a duplicate key', function () {
    app(BuildContentType::class)(contentTypeBlueprintJson('ContentTypeCommandsEntry'));

    $this->artisan('content-type:make', ['key' => 'ContentTypeCommandsEntry'])
        ->expectsQuestion('Libellé singulier', 'Voiture')
        ->expectsQuestion('Libellé pluriel', 'Voitures')
        ->expectsConfirmation('Ce type a-t-il des pages publiques (adressable) ?', 'no')
        ->expectsConfirmation('Ajouter un champ ?', 'no')
        ->expectsConfirmation('Ajouter une relation ?', 'no')
        ->expectsConfirmation('Construire ce Content Type ?', 'yes')
        ->assertExitCode(1);
});
