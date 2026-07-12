<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Construit un Content Type "Car" (champs brand [text, requis] et
 * is_featured [boolean]) et rend son modèle généré chargeable, comme
 * BuildContentTypeTest.php.
 */
function buildCarForAdminCrud(): ContentType
{
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Car',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
        'fields' => [
            ['key' => 'brand', 'type' => 'text', 'required' => true],
            ['key' => 'is_featured', 'type' => 'boolean'],
        ],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $fresh = $contentType->fresh();

    if ($fresh === null) {
        throw new RuntimeException('Expected the newly built Car content type to be refetchable.');
    }

    return $fresh;
}

/**
 * @param  list<string>  $permissions
 */
function contentCrudActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Actor {$counter}",
        'email' => "content-crud-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('lists content entries for a user with content.car.view', function () {
    $contentType = buildCarForAdminCrud();

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $owner = contentCrudActor(['content.car.view']);
    (new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]))->save();

    $this->actingAs($owner, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'cars']))
        ->assertOk()
        ->assertSee('Peugeot');
});

it('denies the index without content.car.view', function () {
    buildCarForAdminCrud();
    $user = contentCrudActor([]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'cars']))
        ->assertForbidden();
});

it('shows the create form to a user with content.car.create', function () {
    buildCarForAdminCrud();
    $user = contentCrudActor(['content.car.view', 'content.car.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'cars']))
        ->assertOk()
        ->assertSee('Brand');
});

it('wires the title field to auto-fill the slug field on an addressable content type', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Post',
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.post.view', 'content.post.create']);

    $response = $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'posts']))
        ->assertOk();

    $response->assertSee('name="slug"', false)
        ->assertSee('document.getElementById(&#039;slug&#039;)', false)
        ->assertSee('baobabSlugify($event.target.value)', false)
        ->assertSee('name="title"', false);
});

it('creates a content entry and sets author_id to the acting user', function () {
    $contentType = buildCarForAdminCrud();
    $user = contentCrudActor(['content.car.view', 'content.car.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'cars']), [
            'brand' => 'Renault',
            'is_featured' => '1',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'cars']));

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = $carClass::query()->first();

    expect($car)->not->toBeNull()
        ->and($car->getAttribute('brand'))->toBe('Renault')
        ->and($car->getAttribute('is_featured'))->toBeTrue()
        ->and($car->getAttribute('author_id'))->toBe($user->id);
});

it('rejects an invalid submission per the declared field rules', function () {
    buildCarForAdminCrud();
    $user = contentCrudActor(['content.car.view', 'content.car.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'cars']), ['brand' => ''])
        ->assertSessionHasErrors('brand');
});

it('requires a slug when the content type is addressable', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'Post',
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
        ],
    ]));
    $module = Module::findOrFail(ContentType::where('key', 'Post')->firstOrFail()->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.post.view', 'content.post.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'posts']), ['title' => 'Hello'])
        ->assertSessionHasErrors('slug');
});

it('auto-increments a conflicting slug instead of rejecting the submission', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Post',
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.post.view', 'content.post.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'posts']), [
            'title' => 'Hello',
            'slug' => 'hello-world',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'posts']));

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'posts']), [
            'title' => 'Hello again',
            'slug' => 'hello-world',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'posts']));

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'posts']), [
            'title' => 'Hello a third time',
            'slug' => 'hello-world',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'posts']));

    /** @var class-string<Model> $postClass */
    $postClass = $contentType->modelClass();

    expect($postClass::query()->where('slug', 'hello-world')->exists())->toBeTrue()
        ->and($postClass::query()->where('slug', 'hello-world-2')->exists())->toBeTrue()
        ->and($postClass::query()->where('slug', 'hello-world-3')->exists())->toBeTrue();
});

it('keeps the same slug when updating an entry without changing it', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Post',
        'label' => ['singular' => 'Article', 'plural' => 'Articles'],
        'is_addressable' => true,
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.post.view', 'content.post.create', 'content.post.update']);

    /** @var class-string<Model> $postClass */
    $postClass = $contentType->modelClass();
    $post = new $postClass(['title' => 'Hello', 'slug' => 'hello-world', 'author_id' => $user->id]);
    $post->save();

    $this->actingAs($user, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'posts', 'entry' => $post->getKey()]), [
            'title' => 'Hello, updated',
            'slug' => 'hello-world',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'posts']));

    expect($post->fresh()?->getAttribute('slug'))->toBe('hello-world')
        ->and($post->fresh()?->getAttribute('title'))->toBe('Hello, updated');
});

it('lets the owner update their own entry with content.car.update', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.car.view', 'content.car.update']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($owner, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $car->getKey()]), [
            'brand' => 'Peugeot 208',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'cars']));

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot 208');
});

it('forbids updating another user\'s entry without update_any', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.car.view']);
    $other = contentCrudActor(['content.car.view', 'content.car.update']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($other, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $car->getKey()]), [
            'brand' => 'Hacked',
        ])
        ->assertForbidden();

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot');
});

it('allows updating another user\'s entry with update_any', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.car.view']);
    $manager = contentCrudActor(['content.car.view', 'content.car.update_any']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($manager, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'cars', 'entry' => $car->getKey()]), [
            'brand' => 'Peugeot 3008',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'cars']));

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot 3008');
});

it('soft deletes an entry on destroy', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.car.view', 'content.car.delete']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();
    $carId = $car->getKey();

    $this->actingAs($owner, 'baobab')
        ->delete(route('admin.content.destroy', ['contentType' => 'cars', 'entry' => $carId]))
        ->assertRedirect(route('admin.content.index', ['contentType' => 'cars']));

    expect($carClass::query()->find($carId))->toBeNull();

    $trashed = $carClass::withTrashed()->find($carId);
    expect($trashed?->getAttribute('deleted_at'))->not->toBeNull();
});
