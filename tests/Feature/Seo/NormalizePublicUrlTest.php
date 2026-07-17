<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Seo\Http\Middleware\NormalizePublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $contentType = app(BuildContentType::class)(carBlueprintJson([
        'key' => 'NormalizeCar',
        'is_addressable' => true,
        'title_field' => 'brand',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();
    $modelClass::create(['brand' => 'Peugeot 208', 'slug' => 'peugeot-208', 'status' => 'published']);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('redirects an uppercase path to its lowercase canonical form', function () {
    $this->get('/Normalize-Cars/Peugeot-208')
        ->assertStatus(301)
        ->assertHeader('Location', url('/normalize-cars/peugeot-208'));
});

it('strips a trailing slash by default', function () {
    // Laravel's test HTTP client (prepareUrlForRequest()) trims the whole
    // URL's trailing slash before dispatch — a trailing-slash request can
    // never actually reach the app through $this->get(). Exercised
    // directly against the middleware instead, with a hand-built Request
    // whose PATH_INFO keeps it (unlike Request::path(), which also trims
    // it — see the middleware's own docblock).
    $request = Request::create('/normalize-cars/peugeot-208/', 'GET');

    $response = (new NormalizePublicUrl)->handle($request, fn () => response('should not be reached'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe(url('/normalize-cars/peugeot-208'));
});

it('appends a trailing slash when configured to do so', function () {
    config(['baobab.redirects.trailing_slash' => 'append']);

    $this->get('/normalize-cars/peugeot-208')
        ->assertStatus(301)
        ->assertHeader('Location', url('/normalize-cars/peugeot-208/'));
});

it('leaves an already-canonical URL untouched', function () {
    $this->get('/normalize-cars/peugeot-208')
        ->assertOk()
        ->assertSee('Peugeot 208');
});

it('preserves the query string when redirecting to the canonical form', function () {
    $this->get('/Normalize-Cars/Peugeot-208?preview=1')
        ->assertStatus(301)
        ->assertHeader('Location', url('/normalize-cars/peugeot-208?preview=1'));
});
