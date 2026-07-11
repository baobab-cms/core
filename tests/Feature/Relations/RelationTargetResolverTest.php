<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('resolves an already-built content type by key', function () {
    $contentType = app(BuildContentType::class)(carBlueprintJson());

    $resolved = (new RelationTargetResolver)->resolve('Car');

    expect($resolved)->toBe([
        'class' => 'Modules\\Car\\Models\\Car',
        'table' => 'ct_cars',
        'key' => 'Car',
    ]);
});

it('resolves the User core model', function () {
    $resolved = (new RelationTargetResolver)->resolve('User');

    expect($resolved)->toBe([
        'class' => User::class,
        'table' => 'users',
        'key' => 'User',
    ]);
});

it('refuses a content type that has not been built yet (no module_id)', function () {
    ContentType::create([
        'key' => 'Draft',
        'table_name' => 'ct_drafts',
        'is_addressable' => false,
        'version' => 1,
        'blueprint' => ['key' => 'Draft', 'label' => ['singular' => 'X', 'plural' => 'Y']],
    ]);

    expect(fn () => (new RelationTargetResolver)->resolve('Draft'))
        ->toThrow(UnknownRelationTargetException::class);
});

it('refuses an unknown target', function () {
    expect(fn () => (new RelationTargetResolver)->resolve('DoesNotExist'))
        ->toThrow(UnknownRelationTargetException::class);
});
