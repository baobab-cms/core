<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function studioActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Actor {$counter}",
        'email' => "studio-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the Studio screens without baobab.system.studio.manage', function () {
    $actor = studioActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.index'))
        ->assertForbidden();

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.create'))
        ->assertForbidden();
});

it('creates a draft from the identity step and redirects to it', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $response = $this->actingAs($actor, 'baobab')->post(route('admin.studio.store'), [
        'name' => 'acme/blog',
        'title' => 'Blog',
        'description' => 'Un module de blog.',
        'icon' => 'bi-box-seam',
        'version' => '1.0.0',
        'authors' => "Ada Lovelace <ada@example.com> (https://example.com)\nCharles Babbage",
    ]);

    $draft = ModuleBlueprintDraft::where('vendor_slug', 'acme/blog')->firstOrFail();

    $response->assertRedirect(route('admin.studio.step.show', [$draft, 1]));

    expect($draft->title)->toBe('Blog')
        ->and($draft->current_step)->toBe(1)
        ->and($draft->blueprint['identity'])->toBe([
            'name' => 'acme/blog',
            'title' => 'Blog',
            'description' => 'Un module de blog.',
            'icon' => 'bi-box-seam',
            'authors' => [
                ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'url' => 'https://example.com'],
                ['name' => 'Charles Babbage'],
            ],
            'version' => '1.0.0',
            'type' => 'module',
        ]);
});

it('forces identity.type to module regardless of what is submitted', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')->post(route('admin.studio.store'), [
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'theme',
    ]);

    $draft = ModuleBlueprintDraft::where('vendor_slug', 'acme/blog')->firstOrFail();

    expect($draft->blueprint['identity']['type'])->toBe('module');
});

it('rejects an invalid vendor/slug name and creates no draft', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.store'), [
            'name' => 'not-a-vendor-slug',
            'title' => 'Blog',
            'version' => '1.0.0',
        ])
        ->assertSessionHasErrors('name');

    expect(ModuleBlueprintDraft::count())->toBe(0);
});

it('rejects a second draft sharing the same vendor/slug name', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.store'), [
            'name' => 'acme/blog',
            'title' => 'Blog (bis)',
            'version' => '1.0.0',
        ])
        ->assertSessionHasErrors('name');

    expect(ModuleBlueprintDraft::count())->toBe(1);
});

it('pre-fills the identity step from an existing draft when resuming', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => [
            'name' => 'acme/blog',
            'title' => 'Blog',
            'version' => '1.0.0',
            'authors' => [['name' => 'Ada Lovelace', 'email' => 'ada@example.com']],
            'type' => 'module',
        ]],
    ]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 1]))
        ->assertOk()
        ->assertSee('acme/blog')
        ->assertSee('Ada Lovelace &lt;ada@example.com&gt;', false);
});

it('re-saving the identity step advances to the next implemented step', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog', 'version' => '1.0.0', 'type' => 'module']],
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.studio.step.update', [$draft, 1]), [
            'name' => 'acme/blog',
            'title' => 'Blog renamed',
            'version' => '1.1.0',
        ])
        ->assertRedirect(route('admin.studio.step.show', [$draft, 2]));

    $draft->refresh();

    expect($draft->title)->toBe('Blog renamed')
        ->and($draft->current_step)->toBe(2)
        ->and($draft->blueprint['identity']['version'])->toBe('1.1.0');
});

it('returns 404 for a step the draft has not reached yet', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog', 'version' => '1.0.0', 'type' => 'module']],
    ]);

    // current_step vaut 1 : l'étape 2 existe désormais (Pass B2) mais reste
    // hors d'atteinte tant qu'elle n'a pas été franchie.
    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 2]))
        ->assertNotFound();
});

it('returns 404 for a step number that no handler implements', function () {
    $actor = studioActor(['baobab.system.studio.manage']);

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'current_step' => 9,
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog', 'version' => '1.0.0', 'type' => 'module']],
    ]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.step.show', [$draft, 9]))
        ->assertNotFound();
});
