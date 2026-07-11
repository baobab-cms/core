<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('builds a content type end to end: table created, module active, permissions real, hook fired', function () {
    $received = null;
    Hook::listen('baobab.content_type.created', function ($contentType) use (&$received): void {
        $received = $contentType;
    });

    $contentType = app(BuildContentType::class)(carBlueprintJson());

    expect($contentType->module_id)->not->toBeNull()
        ->and(Schema::hasTable('ct_cars'))->toBeTrue();

    $module = Module::findOrFail($contentType->module_id);
    expect($module->status)->toBe('active')
        ->and($module->name)->toBe('content-types/cars');

    expect(Permission::where('name', 'content_types.car.view')->where('guard_name', 'baobab')->exists())->toBeTrue();

    expect($received)->not->toBeNull();

    // Prouve que le trou d'autoload M1 comblé en 1b s'intègre réellement
    // avec la sortie du générateur, pas seulement en isolation
    // (ModuleAutoloaderTest.php) : la classe Model générée est chargeable
    // une fois le mapping enregistré, sans avoir été require-ée à la main.
    app(ModuleAutoloader::class)->registerFor($module);
    expect(class_exists('Modules\\Car\\Models\\Car'))->toBeTrue();
});

it('refuses to build a content type with a duplicate key', function () {
    app(BuildContentType::class)(carBlueprintJson());

    expect(fn () => app(BuildContentType::class)(carBlueprintJson()))
        ->toThrow(DuplicateContentTypeException::class);
});
