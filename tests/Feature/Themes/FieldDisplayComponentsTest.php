<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\MediaUsage;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Un composant par type de champ (`FieldType::displayComponent()`, spec 17
 * §4) — celui que le générateur de thèmes injecte dans les templates
 * `single`/`archive` générés. Composants anonymes (props seules) testés via
 * `Blade::render()`, patron `ImgComponentTest.php`.
 */
it('renders the boolean display component', function () {
    expect(trim(Blade::render('<x-baobab::field.boolean-display :value="$value" />', ['value' => true])))->toBe('Oui')
        ->and(trim(Blade::render('<x-baobab::field.boolean-display :value="$value" />', ['value' => false])))->toBe('Non');
});

it('renders the date display component', function () {
    $html = Blade::render('<x-baobab::field.date-display :value="$value" />', ['value' => Carbon::create(2026, 7, 25)]);

    expect(trim($html))->toBe('25/07/2026');
});

it('renders nothing for a null date', function () {
    expect(trim(Blade::render('<x-baobab::field.date-display :value="$value" />', ['value' => null])))->toBe('');
});

it('renders the datetime display component', function () {
    $html = Blade::render('<x-baobab::field.datetime-display :value="$value" />', ['value' => Carbon::create(2026, 7, 25, 14, 30)]);

    expect(trim($html))->toBe('25/07/2026 14:30');
});

it('renders the time display component truncated to hours and minutes', function () {
    expect(trim(Blade::render('<x-baobab::field.time-display :value="$value" />', ['value' => '14:30:00'])))->toBe('14:30');
});

it('renders the decimal display component', function () {
    expect(trim(Blade::render('<x-baobab::field.decimal-display :value="$value" />', ['value' => '19.90'])))->toBe('19.90');
});

it('renders the integer display component', function () {
    expect(trim(Blade::render('<x-baobab::field.integer-display :value="$value" />', ['value' => 42])))->toBe('42');
});

it('renders the text display component', function () {
    expect(trim(Blade::render('<x-baobab::field.text-display :value="$value" />', ['value' => 'Bonjour'])))->toBe('Bonjour');
});

it('renders the textarea display component wrapped in a paragraph', function () {
    expect(trim(Blade::render('<x-baobab::field.textarea-display :value="$value" />', ['value' => 'Un paragraphe.'])))
        ->toBe('<p>Un paragraphe.</p>');
});

it('renders the slug display component', function () {
    expect(trim(Blade::render('<x-baobab::field.slug-display :value="$value" />', ['value' => 'mon-article'])))->toBe('mon-article');
});

it('renders the select display component', function () {
    expect(trim(Blade::render('<x-baobab::field.select-display :value="$value" />', ['value' => 'brouillon'])))->toBe('brouillon');
});

it('renders the radio display component', function () {
    expect(trim(Blade::render('<x-baobab::field.radio-display :value="$value" />', ['value' => 'oui'])))->toBe('oui');
});

it('renders the multiselect display component as a comma-separated list', function () {
    $html = Blade::render('<x-baobab::field.multiselect-display :value="$value" />', ['value' => ['rouge', 'vert', 'bleu']]);

    expect(preg_replace('/\s+/', ' ', trim($html)))->toBe('rouge, vert, bleu');
});

it('renders an empty multiselect display component for no selection', function () {
    expect(trim(Blade::render('<x-baobab::field.multiselect-display />', [])))->toBe('');
});

it('renders the json display component pretty-printed', function () {
    $html = Blade::render('<x-baobab::field.json-display :value="$value" />', ['value' => ['a' => 1]]);

    expect($html)->toContain('<pre>')
        ->and($html)->toContain('&quot;a&quot;: 1');
});

it('renders the richtext display component unescaped', function () {
    $html = Blade::render('<x-baobab::field.richtext-display :value="$value" />', ['value' => '<strong>Gras</strong>']);

    expect(trim($html))->toBe('<strong>Gras</strong>');
});

it('renders the media display component through <x-baobab::img> for a resolved media id', function () {
    Storage::fake('public');

    $actor = User::create(['name' => 'Media Display Actor', 'email' => 'media-display-actor@example.com', 'password' => 'secret']);
    $media = app(UploadMedia::class)(new UploadedFile(createTestJpeg(40, 20), 'photo.jpg', 'image/jpeg', null, true), $actor);

    $html = Blade::render('<x-baobab::field.media-display :value="$value" />', ['value' => $media->id]);

    expect($html)->toContain($media->url());
});

it('renders nothing for a null media display value', function () {
    expect(trim(Blade::render('<x-baobab::field.media-display :value="$value" />', ['value' => null])))->toBe('');
});

it('renders the gallery display component with every media of the field, in order', function () {
    Storage::fake('public');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $actor = User::create(['name' => 'Gallery Display Actor', 'email' => 'gallery-display-actor@example.com', 'password' => 'secret']);
    // Dimensions distinctes : UploadMedia dédoublonne par checksum de contenu
    // (DuplicateMediaDetectedException), et createTestJpeg() est déterministe
    // pour des dimensions identiques.
    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(40, 20), 'first.jpg', 'image/jpeg', null, true), $actor);
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(41, 21), 'second.jpg', 'image/jpeg', null, true), $actor);

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'GalleryDisplayEntry',
        'label' => ['singular' => 'Entrée', 'plural' => 'Entrées'],
        'fields' => [['key' => 'photos', 'type' => 'gallery']],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->status = 'draft';
    $entry->save();

    MediaUsage::create(['media_id' => $second->id, 'usable_type' => $modelClass, 'usable_id' => $entry->getKey(), 'field_key' => 'photos', 'order' => 1]);
    MediaUsage::create(['media_id' => $first->id, 'usable_type' => $modelClass, 'usable_id' => $entry->getKey(), 'field_key' => 'photos', 'order' => 0]);

    $html = Blade::render('<x-baobab::field.gallery-display :entry="$entry" field="photos" />', ['entry' => $entry]);

    $firstPosition = strpos($html, (string) $first->url());
    $secondPosition = strpos($html, (string) $second->url());

    expect($firstPosition)->not->toBeFalse()
        ->and($secondPosition)->not->toBeFalse()
        ->and($firstPosition)->toBeLessThan($secondPosition);

    File::deleteDirectory(generatedModulesPath());
});

it('renders nothing for a gallery field with no media attached', function () {
    Storage::fake('public');
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'EmptyGalleryEntry',
        'label' => ['singular' => 'Entrée', 'plural' => 'Entrées'],
        'fields' => [['key' => 'photos', 'type' => 'gallery']],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    /** @var Model $entry */
    $entry = new $modelClass;
    $entry->status = 'draft';
    $entry->save();

    $html = Blade::render('<x-baobab::field.gallery-display :entry="$entry" field="photos" />', ['entry' => $entry]);

    expect(trim($html))->toBe('');

    File::deleteDirectory(generatedModulesPath());
});
