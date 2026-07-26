<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * @param  list<string>  $permissions
 */
function themeBlueprintActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Theme Blueprint Actor {$counter}",
        'email' => "theme-blueprint-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function themeBlueprintDir(string $slug): string
{
    return base_path("themes/{$slug}");
}

beforeEach(function () {
    File::deleteDirectory(themeBlueprintDir('pest-blueprint-test'));
});

afterEach(function () {
    File::deleteDirectory(themeBlueprintDir('pest-blueprint-test'));
});

it('denies the studio screens without baobab.system.themes.manage', function () {
    $actor = themeBlueprintActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.studio.index'))
        ->assertForbidden();
});

it('lists theme.json blueprints found under themes/', function () {
    File::ensureDirectoryExists(themeBlueprintDir('pest-blueprint-test'));
    File::put(themeBlueprintDir('pest-blueprint-test').'/theme.json', (string) json_encode([
        'name' => 'Pest Blueprint',
        'slug' => 'pest-blueprint-test',
        'supports' => ['search'],
    ]));

    $actor = themeBlueprintActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.themes.studio.index'))
        ->assertOk()
        ->assertSee('Pest Blueprint')
        ->assertSee('search');
});

it('creates a new theme.json from the form', function () {
    $actor = themeBlueprintActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.themes.studio.store'), [
            'name' => 'Pest Blueprint',
            'slug' => 'pest-blueprint-test',
            'menus' => "primary: Navigation principale\nfooter: Pied de page",
            'widget_zones' => 'sidebar: Barre latérale',
            'supports' => ['search'],
        ])
        ->assertRedirect(route('admin.themes.studio.edit', ['slug' => 'pest-blueprint-test']))
        ->assertSessionHas('toast');

    /** @var array<string, mixed> $blueprint */
    $blueprint = json_decode((string) File::get(themeBlueprintDir('pest-blueprint-test').'/theme.json'), associative: true);

    expect($blueprint['name'])->toBe('Pest Blueprint')
        ->and($blueprint['slug'])->toBe('pest-blueprint-test')
        ->and($blueprint['menus'])->toBe(['primary' => 'Navigation principale', 'footer' => 'Pied de page'])
        ->and($blueprint['widget_zones'])->toBe(['sidebar' => 'Barre latérale'])
        ->and($blueprint['supports'])->toBe(['search']);
});

it('refuses to create a theme.json that already exists', function () {
    File::ensureDirectoryExists(themeBlueprintDir('pest-blueprint-test'));
    File::put(themeBlueprintDir('pest-blueprint-test').'/theme.json', (string) json_encode([
        'name' => 'Existing',
        'slug' => 'pest-blueprint-test',
    ]));

    $actor = themeBlueprintActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.themes.studio.store'), [
            'name' => 'Overwrite Attempt',
            'slug' => 'pest-blueprint-test',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('blueprint');

    /** @var array<string, mixed> $blueprint */
    $blueprint = json_decode((string) File::get(themeBlueprintDir('pest-blueprint-test').'/theme.json'), associative: true);

    expect($blueprint['name'])->toBe('Existing');
});

it('updates an existing theme.json while preserving content_types and tokens', function () {
    File::ensureDirectoryExists(themeBlueprintDir('pest-blueprint-test'));
    File::put(themeBlueprintDir('pest-blueprint-test').'/theme.json', (string) json_encode([
        'name' => 'Pest Blueprint',
        'slug' => 'pest-blueprint-test',
        'menus' => ['primary' => 'Navigation'],
        'tokens' => ['colors' => ['primary' => '#123456']],
        'content_types' => ['Article' => ['templates' => ['show']]],
    ]));

    $actor = themeBlueprintActor(['baobab.system.themes.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.themes.studio.update', ['slug' => 'pest-blueprint-test']), [
            'name' => 'Pest Blueprint Renamed',
            'menus' => 'primary: Navigation principale',
            'supports' => ['search'],
        ])
        ->assertRedirect(route('admin.themes.studio.edit', ['slug' => 'pest-blueprint-test']))
        ->assertSessionHas('toast');

    /** @var array<string, mixed> $blueprint */
    $blueprint = json_decode((string) File::get(themeBlueprintDir('pest-blueprint-test').'/theme.json'), associative: true);

    expect($blueprint['name'])->toBe('Pest Blueprint Renamed')
        ->and($blueprint['menus'])->toBe(['primary' => 'Navigation principale'])
        ->and($blueprint['supports'])->toBe(['search'])
        ->and($blueprint['tokens'])->toBe(['colors' => ['primary' => '#123456']])
        ->and($blueprint['content_types'])->toBe(['Article' => ['templates' => ['show']]]);
});
