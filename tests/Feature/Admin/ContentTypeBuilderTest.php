<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Le Content Type builder (M8 point 2, Pass B) — l'écran, pas le pipeline.
 * Ce qui est vérifié ici est ce qu'un adaptateur mince doit garantir :
 * l'écran atteint bien les Actions existantes, et il ne s'autorise rien
 * qu'elles interdisent.
 *
 * @param  list<string>  $permissions
 */
function builderActor(array $permissions = ['baobab.system.content_types.manage']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Builder Actor {$counter}",
        'email' => "builder-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function builderPayload(array $overrides = []): array
{
    return [
        'key' => 'Gizmo',
        'label_singular' => 'Gadget',
        'label_plural' => 'Gadgets',
        'fields' => json_encode([
            ['key' => 'title', 'type' => 'text', 'required' => true, 'unique' => false, 'indexed' => false],
        ]),
        'relations' => json_encode([]),
        ...$overrides,
    ];
}

afterEach(function (): void {
    $path = config('baobab.content_types.modules_path');

    if (is_string($path) && File::isDirectory($path)) {
        File::deleteDirectory($path);
    }
});

it('lists the content types that exist, and offers to create one', function (): void {
    $this->actingAs(builderActor(), 'baobab')
        ->get('/admin/content-types')
        ->assertOk()
        ->assertSee('Types de contenu')
        ->assertSee(route('admin.content-types.create'), escape: false);
});

it('renders the create form with the shared field editor and the field catalogue', function (): void {
    $response = $this->actingAs(builderActor(), 'baobab')
        ->get('/admin/content-types/create')
        ->assertOk();

    // L'éditeur partagé est bien celui du Studio : mêmes libellés, mêmes
    // primitives Alpine — c'est ce qui distingue une extraction d'une copie.
    $response->assertSee('addField()', escape: false);
    $response->assertSee('needsChoices(field.type)', escape: false);

    foreach (['text', 'richtext', 'select', 'image'] as $type) {
        $response->assertSee('<option value="'.$type.'">', escape: false);
    }
});

it('builds a real content type through BuildContentType, with its table and its permissions', function (): void {
    $this->actingAs(builderActor(), 'baobab')
        ->post('/admin/content-types', builderPayload())
        ->assertRedirect();

    $type = ContentType::where('key', 'Gizmo')->first();

    expect($type)->not->toBeNull()
        ->and($type->table_name)->toBe('ct_gizmos')
        // Le module est installé et activé : un type construit mais non
        // branché est précisément l'incompréhension signalée au n° 99.
        ->and($type->module_id)->not->toBeNull();

    expect(Schema::hasTable('ct_gizmos'))->toBeTrue();
    expect(Schema::hasColumn('ct_gizmos', 'title'))->toBeTrue();
});

it('refuses a key that is not English singular PascalCase, before reaching the action', function (): void {
    $this->actingAs(builderActor(), 'baobab')
        ->post('/admin/content-types', builderPayload(['key' => 'gizmo']))
        ->assertSessionHasErrors('key');

    expect(ContentType::where('key', 'gizmo')->exists())->toBeFalse();
});

it('surfaces a blueprint rejection as a form error rather than a 500', function (): void {
    // `is_addressable` sans `title_field` : refusé par ContentTypeBlueprint,
    // pas par les règles de surface — c'est le chemin qu'on veut voir remonter.
    $this->actingAs(builderActor(), 'baobab')
        ->post('/admin/content-types', builderPayload(['is_addressable' => '1']))
        ->assertSessionHasErrors('blueprint');

    expect(ContentType::where('key', 'Gizmo')->exists())->toBeFalse();
});

it('evolves an existing type by adding a field, and keeps its data', function (): void {
    $actor = builderActor();

    $this->actingAs($actor, 'baobab')
        ->post('/admin/content-types', builderPayload())
        ->assertRedirect();

    $type = ContentType::where('key', 'Gizmo')->firstOrFail();

    DB::table('ct_gizmos')->insert(['title' => 'Déjà là', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($actor, 'baobab')
        ->put('/admin/content-types/Gizmo', builderPayload([
            'fields' => json_encode([
                ['key' => 'title', 'type' => 'text', 'required' => true, 'unique' => false, 'indexed' => false],
                ['key' => 'reference', 'type' => 'text', 'required' => false, 'unique' => false, 'indexed' => false],
            ]),
        ]))
        ->assertRedirect();

    expect(Schema::hasColumn('ct_gizmos', 'reference'))->toBeTrue()
        ->and($type->fresh()->version)->toBe(2)
        ->and(DB::table('ct_gizmos')->where('title', 'Déjà là')->exists())->toBeTrue();
});

it('refuses to drop a field without an explicit confirmation', function (): void {
    $actor = builderActor();

    $this->actingAs($actor, 'baobab')
        ->post('/admin/content-types', builderPayload([
            'fields' => json_encode([
                ['key' => 'title', 'type' => 'text', 'required' => true, 'unique' => false, 'indexed' => false],
                ['key' => 'doomed', 'type' => 'text', 'required' => false, 'unique' => false, 'indexed' => false],
            ]),
        ]))
        ->assertRedirect();

    $this->actingAs($actor, 'baobab')
        ->put('/admin/content-types/Gizmo', builderPayload())
        ->assertSessionHasErrors('destructive');

    expect(Schema::hasColumn('ct_gizmos', 'doomed'))->toBeTrue();
});

it('denies every screen without the content types permission', function (): void {
    $actor = builderActor(permissions: []);

    $this->actingAs($actor, 'baobab')->get('/admin/content-types')->assertForbidden();
    $this->actingAs($actor, 'baobab')->get('/admin/content-types/create')->assertForbidden();
    $this->actingAs($actor, 'baobab')->post('/admin/content-types', builderPayload())->assertForbidden();
});
