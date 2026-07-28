<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Studio\Exceptions\UnknownStudioRelationTargetException;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
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

it('resolves a sibling entity declared in the same blueprint', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    $resolved = $resolver->resolve('entity:Driver', [
        ['key' => 'Car', 'table' => 'cars'],
        ['key' => 'Driver', 'table' => 'drivers'],
    ]);

    expect($resolved)->toBe(['class' => null, 'table' => 'drivers', 'key' => 'Driver']);
});

it('refuses an entity target absent from the current blueprint', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    expect(fn () => $resolver->resolve('entity:DoesNotExist', [
        ['key' => 'Car', 'table' => 'cars'],
    ]))->toThrow(UnknownStudioRelationTargetException::class);
});

it('delegates content_type: targets to the already-built Content Type resolver', function () {
    app(BuildContentType::class)(carBlueprintJson());

    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    $resolved = $resolver->resolve('content_type:Car', []);

    expect($resolved)->toBe([
        'class' => 'Modules\\Car\\Models\\Car',
        'table' => 'ct_cars',
        'key' => 'Car',
    ]);
});

it('refuses a content_type: target that has not been built yet', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    expect(fn () => $resolver->resolve('content_type:DoesNotExist', []))
        ->toThrow(UnknownStudioRelationTargetException::class);
});

it('delegates core: targets to the Core model resolver', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    $resolved = $resolver->resolve('core:User', []);

    expect($resolved)->toBe(['class' => User::class, 'table' => 'users', 'key' => 'User']);
});

it('refuses a core: target that is not a known Core model', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    expect(fn () => $resolver->resolve('core:DoesNotExist', []))
        ->toThrow(UnknownStudioRelationTargetException::class);
});

it('refuses a target without a recognized prefix', function () {
    $resolver = new StudioRelationTargetResolver(new RelationTargetResolver);

    expect(fn () => $resolver->resolve('User', []))
        ->toThrow(UnknownStudioRelationTargetException::class);
});
