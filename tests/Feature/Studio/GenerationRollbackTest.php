<?php

use Baobab\Modules\Models\Module;
use Baobab\Studio\Actions\GenerateModuleFromDraft;
use Baobab\Studio\Exceptions\ModuleGenerationFailedException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi n° 147 et n° 148 — une génération qui échoue ne doit ni servir une
 * trace PHP, ni laisser derrière elle un état que la relance ne rattrape pas.
 *
 * **Aucun double ici**, et ce n'est pas un choix de style : les Actions du Core
 * sont `final` et typées au constructeur, ce qui interdit de leur substituer un
 * objet de test. La contrainte est heureuse — l'échec provoqué ci-dessous est
 * celui que le produit rencontre vraiment, et c'est littéralement l'incident du
 * 14 août 2026 : un nom de module déjà pris.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Un nom de module et une table par test, pour la raison habituelle : une
 * table n'a qu'une migration de création au nom déterministe (n° 120), et un
 * nom partagé ferait fixer le schéma par le premier test du processus.
 */
function rollbackSuffix(bool $next = false): string
{
    static $counter = 0;

    if ($next) {
        $counter++;
    }

    return (string) $counter;
}

function rollbackDraft(): ModuleBlueprintDraft
{
    $suffix = rollbackSuffix(next: true);

    return ModuleBlueprintDraft::create([
        'vendor_slug' => "garage/rollback{$suffix}",
        'title' => 'Rollback',
        'current_step' => 9,
        'blueprint' => [
            'blueprint_version' => 1,
            'identity' => ['name' => "garage/rollback{$suffix}", 'title' => 'Rollback', 'version' => '1.0.0', 'type' => 'module'],
            'entities' => [[
                'key' => 'Crate',
                'table' => 'rbcrates'.$suffix,
                'fields' => [['key' => 'label', 'type' => 'text', 'required' => true]],
                'relations' => [],
                'routes' => ['admin' => true, 'front' => false, 'api' => false],
            ]],
            'permissions' => ['auto_crud' => true, 'custom' => []],
        ],
    ]);
}

function rollbackModuleDirectory(): string
{
    return generatedModulesPath().'/garage-rollback'.rollbackSuffix();
}

function rollbackTable(): string
{
    return 'rbcrates'.rollbackSuffix();
}

/**
 * Un module homonyme, installé avant, qui fera échouer l'installation sur
 * `modules_name_unique` — et qui ne doit pas en souffrir.
 */
function rollbackIntruder(ModuleBlueprintDraft $draft): Module
{
    return Module::create([
        'name' => $draft->vendor_slug,
        'title' => 'Un module homonyme, installé avant',
        'type' => 'module',
        'version' => '2.0.0',
        'provider' => 'Acme\\Homonyme\\Providers\\HomonymeServiceProvider',
        'source' => 'local',
        'path' => generatedModulesPath().'/quelqu-un-dautre',
        'manifest' => ['name' => $draft->vendor_slug],
        'status' => 'active',
        'installed_at' => now(),
    ]);
}

/**
 * Le défaut d'origine, vu depuis l'utilisateur : l'écran doit dire ce qui
 * s'est passé, et non servir une `UniqueConstraintViolationException` avec sa
 * trace, sa requête SQL et le manifeste entier (suivi n° 147).
 */
it('turns a failed installation into a message written for a human', function () {
    $draft = rollbackDraft();
    rollbackIntruder($draft);

    try {
        app(GenerateModuleFromDraft::class)($draft);
        $this->fail('La génération aurait dû échouer à l\'installation.');
    } catch (ModuleGenerationFailedException $e) {
        expect($e->step)->toBe('install')
            ->and($e->getMessage())->not->toContain('SQLSTATE')
            ->and($e->getMessage())->not->toContain('Exception')
            ->and($e->getMessage())->toContain('relancez')
            // La cause reste accessible au journal technique et aux tests,
            // jamais à l'écran.
            ->and($e->getPrevious())->not->toBeNull();
    }
});

/**
 * **Le cas qui a fait trouver un défaut dans la compensation elle-même.** Une
 * compensation naïve constaterait « il y a une ligne `modules` » et
 * désinstallerait le module de quelqu'un d'autre, avec ses tables, ses données
 * et ses fichiers : plus destructrice que le défaut qu'elle répare. On ne
 * défait que ce qu'on a fait.
 */
it('never touches a module that already owned the name', function () {
    $draft = rollbackDraft();
    $intruder = rollbackIntruder($draft);

    try {
        app(GenerateModuleFromDraft::class)($draft);
    } catch (ModuleGenerationFailedException) {
        // attendu
    }

    $survivor = Module::find($intruder->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->title)->toBe('Un module homonyme, installé avant')
        ->and($survivor?->status)->toBe('active');
});

/**
 * Ce que la compensation retire, en revanche, c'est tout ce qu'elle a écrit —
 * y compris la table créée par des migrations jouées **avant** que la ligne
 * `modules` n'échoue à naître. C'est ce qui rend « tout a été annulé » vrai.
 */
it('removes the files and the tables of the attempt that failed', function () {
    $draft = rollbackDraft();
    rollbackIntruder($draft);

    try {
        app(GenerateModuleFromDraft::class)($draft);
    } catch (ModuleGenerationFailedException) {
        // attendu
    }

    expect(Schema::hasTable(rollbackTable()))->toBeFalse()
        ->and(File::isDirectory(rollbackModuleDirectory()))->toBeFalse()
        ->and($draft->fresh()?->isGenerated())->toBeFalse();
});

/**
 * L'objectif produit énoncé par l'utilisateur : « tout annuler et présenter
 * l'erreur pour qu'il corrige et relance sans elle ». Empêcher les dégâts ne
 * suffirait pas — la relance doit aboutir, sur le même brouillon.
 */
it('lets the very same draft succeed once the obstacle is removed', function () {
    $draft = rollbackDraft();
    $intruder = rollbackIntruder($draft);

    try {
        app(GenerateModuleFromDraft::class)($draft);
    } catch (ModuleGenerationFailedException) {
        // attendu
    }

    // L'utilisateur retire le module homonyme, puis relance sans rien changer
    // à son brouillon.
    $intruder->delete();

    app(GenerateModuleFromDraft::class)($draft);

    expect(Module::where('name', $draft->vendor_slug)->first()?->status)->toBe('active')
        ->and(Schema::hasTable(rollbackTable()))->toBeTrue()
        ->and($draft->fresh()?->isGenerated())->toBeTrue();
});

/**
 * La compensation ne vaut **que** pour la première génération : sur une
 * régénération, retirer les fichiers effacerait un module qui existait avant
 * et fonctionnait. Écart borné et assumé (suivi n° 203).
 */
it('never destroys an already installed module when a regeneration fails', function () {
    $draft = rollbackDraft();

    app(GenerateModuleFromDraft::class)($draft);
    $draft = $draft->fresh() ?? $draft;

    expect($draft->isGenerated())->toBeTrue();

    // Une régénération vers un blueprint invalide : elle échoue, mais le
    // module installé doit rester intact.
    $blueprint = $draft->blueprint;
    $blueprint['entities'][0]['fields'] = [];
    $draft->blueprint = $blueprint;
    $draft->save();

    try {
        app(GenerateModuleFromDraft::class)($draft);
    } catch (Throwable) {
        // attendu
    }

    expect(Module::where('name', $draft->vendor_slug)->exists())->toBeTrue()
        ->and(Schema::hasTable(rollbackTable()))->toBeTrue()
        ->and(File::isDirectory(rollbackModuleDirectory()))->toBeTrue();
});
