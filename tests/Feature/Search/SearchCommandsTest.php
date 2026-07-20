<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('baobab:search:reindex runs without error for a searchable Content Type', function () {
    buildApiCar([
        'key' => 'SearchableCar',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true]],
    ]);

    test()->artisan('baobab:search:reindex')->assertExitCode(0);
});

it('baobab:search:reindex reports failure for an unknown --source', function () {
    test()->artisan('baobab:search:reindex', ['--source' => 'DoesNotExist'])->assertExitCode(1);
});

it('baobab:search:status reports the active driver and per-type volume', function () {
    [, $carClass] = buildApiCar([
        'key' => 'SearchableCar',
        'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true, 'searchable' => true]],
    ]);
    $carClass::create(['brand' => 'Peugeot', 'slug' => 'peugeot', 'status' => 'published']);

    test()->artisan('baobab:search:status')
        ->expectsOutputToContain('database')
        ->assertExitCode(0);
});
