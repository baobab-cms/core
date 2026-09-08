<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Media\Models\MediaUsage;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Construit un Content Type « AdminCrudEntry » (champs brand [text, requis] et
 * is_featured [boolean]) et rend son modèle généré chargeable, comme
 * BuildContentTypeTest.php.
 */
function buildCarForAdminCrud(): ContentType
{
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'AdminCrudEntry',
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
        throw new RuntimeException('Expected the newly built AdminCrudEntry content type to be refetchable.');
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

it('lists content entries for a user with content.admin_crud_entry.view', function () {
    $contentType = buildCarForAdminCrud();

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $owner = contentCrudActor(['content.admin_crud_entry.view']);
    (new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]))->save();

    $this->actingAs($owner, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'admin-crud-entries']))
        ->assertOk()
        ->assertSee('Peugeot');
});

/**
 * n° 133 — la liste admin affichait des valeurs brutes (`1`/`0` pour un
 * booléen, du HTML richtext en clair) au lieu des composants d'affichage.
 * Content Type dédié (schéma propre, pas `AdminCrudEntry`/`Car`) : `headline`
 * (text, tronqué), `is_featured` (boolean, « Oui »/« Non »), `body`
 * (richtext, exclu des colonnes de liste — jamais rendu, jamais de surface
 * d'injection à réexaminer).
 */
it('renders list columns through display components, excluding richtext from the list entirely', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'AdminListDisplay',
        'label' => ['singular' => 'Article de liste', 'plural' => 'Articles de liste'],
        'fields' => [
            ['key' => 'headline', 'type' => 'text', 'required' => true],
            ['key' => 'is_featured', 'type' => 'boolean'],
            ['key' => 'body', 'type' => 'richtext'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->fresh()->modelClass();
    $owner = contentCrudActor(['content.admin_list_display.view']);
    (new $modelClass([
        'headline' => str_repeat('Un titre bien trop long pour une colonne. ', 5),
        'is_featured' => true,
        'body' => '<script>alert(1)</script>',
        'author_id' => $owner->id,
    ]))->save();

    $response = $this->actingAs($owner, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'admin-list-displays']));

    $response->assertOk()
        ->assertSee('Oui')
        ->assertDontSee('alert(1)', false)
        ->assertDontSee('<script>', false);
});

it('denies the index without content.admin_crud_entry.view', function () {
    buildCarForAdminCrud();
    $user = contentCrudActor([]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.index', ['contentType' => 'admin-crud-entries']))
        ->assertForbidden();
});

it('shows the create form to a user with content.admin_crud_entry.create', function () {
    buildCarForAdminCrud();
    $user = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'admin-crud-entries']))
        ->assertOk()
        ->assertSee('Brand');
});

it('renders a real Tiptap editor container for a richtext field, not a plain textarea', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'BlogPost',
        'label' => ['singular' => 'BlogPost', 'plural' => 'BlogPosts'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'body', 'type' => 'richtext'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.blog_post.view', 'content.blog_post.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'blog_posts']))
        ->assertOk()
        ->assertSee('data-tiptap-editor', false);
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
    $user = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'admin-crud-entries']), [
            'brand' => 'Renault',
            'is_featured' => '1',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'admin-crud-entries']));

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
    $user = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'admin-crud-entries']), ['brand' => ''])
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

it('lets the owner update their own entry with content.admin_crud_entry.update', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.update']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($owner, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'admin-crud-entries', 'entry' => $car->getKey()]), [
            'brand' => 'Peugeot 208',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'admin-crud-entries']));

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot 208');
});

it('forbids updating another user\'s entry without update_any', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.admin_crud_entry.view']);
    $other = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.update']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($other, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'admin-crud-entries', 'entry' => $car->getKey()]), [
            'brand' => 'Hacked',
        ])
        ->assertForbidden();

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot');
});

it('allows updating another user\'s entry with update_any', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.admin_crud_entry.view']);
    $manager = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.update_any']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();

    $this->actingAs($manager, 'baobab')
        ->put(route('admin.content.update', ['contentType' => 'admin-crud-entries', 'entry' => $car->getKey()]), [
            'brand' => 'Peugeot 3008',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'admin-crud-entries']));

    expect($car->fresh()?->getAttribute('brand'))->toBe('Peugeot 3008');
});

it('soft deletes an entry on destroy', function () {
    $contentType = buildCarForAdminCrud();
    $owner = contentCrudActor(['content.admin_crud_entry.view', 'content.admin_crud_entry.delete']);

    /** @var class-string<Model> $carClass */
    $carClass = $contentType->modelClass();
    $car = new $carClass(['brand' => 'Peugeot', 'author_id' => $owner->id]);
    $car->save();
    $carId = $car->getKey();

    $this->actingAs($owner, 'baobab')
        ->delete(route('admin.content.destroy', ['contentType' => 'admin-crud-entries', 'entry' => $carId]))
        ->assertRedirect(route('admin.content.index', ['contentType' => 'admin-crud-entries']));

    expect($carClass::query()->find($carId))->toBeNull();

    $trashed = $carClass::withTrashed()->find($carId);
    expect($trashed?->getAttribute('deleted_at'))->not->toBeNull();
});

// ── champs image/file (M4 point 4b-i) ──────────────────────────────────────────

it('saves a content entry with a valid image field value', function () {
    Storage::fake('public');

    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Poster',
        'label' => ['singular' => 'Poster', 'plural' => 'Posters'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'cover', 'type' => 'image'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.poster.view', 'content.poster.create']);

    $media = app(UploadMedia::class)(
        new UploadedFile(createTestJpeg(200, 100), 'cover.jpg', 'image/jpeg', null, true),
        $user,
    );

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'posters']), [
            'title' => 'Affiche',
            'cover' => $media->id,
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'posters']));

    /** @var class-string<Model> $posterClass */
    $posterClass = $contentType->modelClass();
    $poster = $posterClass::query()->first();

    expect($poster?->getAttribute('cover'))->toBe($media->id);
});

it('rejects a content entry referencing a media that does not exist for an image field', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Flyer',
        'label' => ['singular' => 'Flyer', 'plural' => 'Flyers'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'cover', 'type' => 'image'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.flyer.view', 'content.flyer.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'flyers']), [
            'title' => 'Flyer',
            'cover' => 999999,
        ])
        ->assertSessionHasErrors('cover');

    /** @var class-string<Model> $flyerClass */
    $flyerClass = $contentType->modelClass();
    expect($flyerClass::query()->count())->toBe(0);
});

it('renders the media picker container for an image field', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Banner',
        'label' => ['singular' => 'Banner', 'plural' => 'Banners'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'cover', 'type' => 'image'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.banner.view', 'content.banner.create']);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'banners']))
        ->assertOk()
        ->assertSee('mediaPicker(', false);
});

// ── champ gallery (M4 point 4b-ii) ─────────────────────────────────────────────

it('saves a content entry with a valid gallery field selection', function () {
    Storage::fake('public');

    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Album',
        'label' => ['singular' => 'Album', 'plural' => 'Albums'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.album.view', 'content.album.create']);

    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), $user);
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), $user, [], 'new');

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'albums']), [
            'title' => 'Vacances',
            'photos' => [$first->id, $second->id],
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'albums']));

    /** @var class-string<Model> $albumClass */
    $albumClass = $contentType->modelClass();
    $album = $albumClass::query()->first();

    $usages = MediaUsage::where('usable_type', $album->getMorphClass())
        ->where('usable_id', $album->getKey())
        ->where('field_key', 'photos')
        ->orderBy('order')
        ->get();

    expect($usages)->toHaveCount(2)
        ->and($usages->get(0)?->media_id)->toBe($first->id)
        ->and($usages->get(1)?->media_id)->toBe($second->id);
});

it('rejects a content entry referencing a media that does not exist in a gallery field', function () {
    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Portfolio',
        'label' => ['singular' => 'Portfolio', 'plural' => 'Portfolios'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.portfolio.view', 'content.portfolio.create']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'portfolios']), [
            'title' => 'Travaux',
            'photos' => [999999],
        ])
        ->assertSessionHasErrors('photos.0');

    /** @var class-string<Model> $portfolioClass */
    $portfolioClass = $contentType->modelClass();
    expect($portfolioClass::query()->count())->toBe(0)
        ->and(MediaUsage::where('field_key', 'photos')->count())->toBe(0);
});

it('renders the existing gallery selection in order on the edit screen', function () {
    Storage::fake('public');

    $contentType = app(BuildContentType::class)((string) json_encode([
        'key' => 'Gallery',
        'label' => ['singular' => 'Gallery', 'plural' => 'Galleries'],
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'photos', 'type' => 'gallery'],
        ],
    ]));
    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    $user = contentCrudActor(['content.gallery.view', 'content.gallery.create', 'content.gallery.update']);

    $first = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'first.jpg', 'image/jpeg', null, true), $user);
    $second = app(UploadMedia::class)(new UploadedFile(createTestJpeg(), 'second.jpg', 'image/jpeg', null, true), $user, [], 'new');

    $this->actingAs($user, 'baobab')->post(route('admin.content.store', ['contentType' => 'galleries']), [
        'title' => 'Souvenirs',
        'photos' => [$second->id, $first->id],
    ]);

    /** @var class-string<Model> $galleryClass */
    $galleryClass = $contentType->modelClass();
    $entry = $galleryClass::query()->first();

    $response = $this->actingAs($user, 'baobab')
        ->get(route('admin.content.edit', ['contentType' => 'galleries', 'entry' => $entry->getKey()]))
        ->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('second.jpg')
        ->and($html)->toContain('first.jpg')
        ->and(strpos($html, 'second.jpg'))->toBeLessThan(strpos($html, 'first.jpg'));
});

/**
 * L'autre moitié du n° 140 : la fiche de Content Type ne savait pas davantage
 * saisir une relation — ni son gabarit ni son contrôleur ne contenaient la
 * moindre occurrence de « relation », alors que les Content Types en portent
 * depuis M3. Les deux chemins partagent désormais le même composant.
 */
it('renders a relation as a select of the target entries on the content form', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'RelationTargetBrand',
        'label' => ['singular' => 'Marque', 'plural' => 'Marques'],
        'fields' => [['key' => 'name', 'type' => 'text', 'required' => true]],
    ]));

    $brandType = ContentType::where('key', 'RelationTargetBrand')->firstOrFail();
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($brandType->module_id));

    /** @var class-string<Model> $brandClass */
    $brandClass = $brandType->modelClass();
    $brand = $brandClass::create(['name' => 'Peugeot', 'status' => 'published']);

    $type = app(BuildContentType::class)((string) json_encode([
        'key' => 'RelationOwningCar',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
        'fields' => [['key' => 'model', 'type' => 'text', 'required' => true]],
        'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'RelationTargetBrand']],
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($type->module_id));

    $actor = contentCrudActor(['content.relation_owning_car.create']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.content.create', ['contentType' => 'relation-owning-cars']))
        ->assertOk()
        ->assertSee('name="brand_id"', false)
        // Le libellé vient de la relation, et l'option de la cible réelle — pas
        // un identifiant nu.
        ->assertSee('Brand', false)
        ->assertSee('value="'.$brand->getKey().'"', false)
        ->assertSee('Peugeot', false);
});

it('refuses a relation pointing at an entry that does not exist', function () {
    app(BuildContentType::class)((string) json_encode([
        'key' => 'RelationCheckedBrand',
        'label' => ['singular' => 'Marque', 'plural' => 'Marques'],
        'fields' => [['key' => 'name', 'type' => 'text', 'required' => true]],
    ]));

    $type = app(BuildContentType::class)((string) json_encode([
        'key' => 'RelationCheckedCar',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
        'fields' => [['key' => 'model', 'type' => 'text', 'required' => true]],
        'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'RelationCheckedBrand']],
    ]));

    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($type->module_id));

    $actor = contentCrudActor(['content.relation_checked_car.create']);

    // La colonne était `fillable` sans être validée : une clé étrangère
    // inventée n'échouait qu'en base, en 500.
    $this->actingAs($actor, 'baobab')
        ->post(route('admin.content.store', ['contentType' => 'relation-checked-cars']), ['model' => 'e-208', 'brand_id' => 999999])
        ->assertSessionHasErrors('brand_id');
});
