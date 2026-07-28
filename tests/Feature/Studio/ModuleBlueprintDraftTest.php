<?php

use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Database\QueryException;

it('persists a draft with its default step and no module yet', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/fleet',
        'title' => 'Fleet',
        'blueprint' => ['identity' => ['name' => 'garage/fleet', 'title' => 'Fleet']],
    ]);

    $draft->refresh();

    expect($draft->current_step)->toBe(1)
        ->and($draft->blueprint_version)->toBe(1)
        ->and($draft->blueprint)->toBe(['identity' => ['name' => 'garage/fleet', 'title' => 'Fleet']])
        ->and($draft->module_id)->toBeNull()
        ->and($draft->generated_at)->toBeNull()
        ->and($draft->isGenerated())->toBeFalse();
});

it('rejects two drafts sharing the same vendor_slug', function () {
    ModuleBlueprintDraft::create(['vendor_slug' => 'garage/fleet', 'title' => 'Fleet', 'blueprint' => []]);

    expect(fn () => ModuleBlueprintDraft::create(['vendor_slug' => 'garage/fleet', 'title' => 'Fleet (bis)', 'blueprint' => []]))
        ->toThrow(QueryException::class);
});

it('reports as generated once module_id and generated_at are set', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/fleet',
        'title' => 'Fleet',
        'blueprint' => [],
        'generated_at' => now(),
    ]);

    expect($draft->isGenerated())->toBeTrue();
});

it('applies the (currently empty) blueprint migrators as a no-op', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/fleet',
        'title' => 'Fleet',
        'blueprint' => ['blueprint_version' => 1, 'identity' => ['name' => 'garage/fleet']],
    ]);

    expect($draft->migratedBlueprint())->toBe(['blueprint_version' => 1, 'identity' => ['name' => 'garage/fleet']]);
});

it('exposes its blueprint as a permissively-validated value object', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/fleet',
        'title' => 'Fleet',
        'blueprint' => ['identity' => ['name' => 'garage/fleet', 'title' => 'Fleet']],
    ]);

    expect($draft->asBlueprint()->name())->toBe('garage/fleet')
        ->and($draft->asBlueprint()->entities())->toBe([]);
});

it('derives a single-level module directory from the vendor/slug name', function () {
    config(['baobab.studio.modules_path' => sys_get_temp_dir().'/baobab-test-studio-modules']);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'garage/fleet',
        'title' => 'Fleet',
        'blueprint' => [],
    ]);

    expect($draft->moduleDir())->toBe(sys_get_temp_dir().'/baobab-test-studio-modules/garage-fleet');
});
