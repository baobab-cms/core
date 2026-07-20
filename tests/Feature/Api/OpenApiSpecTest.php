<?php

use Baobab\ContentTypes\Fields\Types\GalleryField;
use Baobab\ContentTypes\Fields\Types\IntegerField;
use Baobab\ContentTypes\Fields\Types\SelectField;
use Baobab\ContentTypes\Fields\Types\TextField;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('serves a valid OpenAPI 3.1 document with the Core error components', function () {
    $response = test()->getJson('/api/v1/openapi.json');

    $response->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('components.schemas.ProblemDetails.required', ['type', 'title', 'status']);
});

it('includes an active Content Type with its exposed fields, excluding internal_note', function () {
    buildApiCar();

    $response = test()->getJson('/api/v1/openapi.json');

    $response->assertOk()
        ->assertJsonPath('components.schemas.ApiCar.properties.brand.type', 'string')
        ->assertJsonPath('components.schemas.ApiCar.properties.brand.maxLength', 255)
        ->assertJsonPath('components.schemas.ApiCar.required', fn (array $required) => in_array('brand', $required, true))
        ->assertJsonPath('components.schemas.ApiCar.properties.price.type', 'number')
        ->assertJsonPath('components.schemas.ApiCar.properties.price.nullable', true)
        ->assertJsonMissingPath('components.schemas.ApiCar.properties.internal_note')
        ->assertJsonPath('paths./content/api-cars.get.tags.0', 'ApiCar')
        ->assertJsonPath('paths./content/api-cars/{entry}.patch.summary', fn (string $summary) => str_contains($summary, 'Voitures'));
});

it('omits a Content Type whose api_enabled is disabled', function () {
    buildApiCar(['key' => 'ApiCarDisabled', 'api_enabled' => false]);

    $response = test()->getJson('/api/v1/openapi.json');

    $response->assertOk()
        ->assertJsonMissingPath('components.schemas.ApiCarDisabled')
        ->assertJsonMissingPath('paths./content/api-car-disableds');
});

it('exports the document to storage/app/baobab/openapi/openapi.json', function () {
    buildApiCar();

    $path = storage_path('app/baobab/openapi/openapi.json');
    File::delete($path);

    test()->artisan('baobab:api:openapi')->assertExitCode(0);

    expect(File::exists($path))->toBeTrue();

    $document = json_decode((string) File::get($path), true);

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['components']['schemas'])->toHaveKey('ApiCar');
});

it('describes text fields with maxLength, select fields with enum, integer fields with bounds, and gallery as an array', function () {
    expect((new TextField)->openApiSchema(['max_length' => 100]))->toBe(['type' => 'string', 'maxLength' => 100])
        ->and((new SelectField)->openApiSchema(['choices' => ['a', 'b']]))->toBe(['type' => 'string', 'enum' => ['a', 'b']])
        ->and((new IntegerField)->openApiSchema(['min' => 1, 'max' => 10]))->toBe(['type' => 'integer', 'minimum' => 1, 'maximum' => 10])
        ->and((new GalleryField)->openApiSchema([])['type'])->toBe('array');
});
