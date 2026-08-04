<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function studioDraftActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Studio Draft Actor {$counter}",
        'email' => "studio-draft-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('lists existing drafts on the index screen', function () {
    ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
    ]);

    $actor = studioDraftActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.index'))
        ->assertOk()
        ->assertSee('Blog')
        ->assertSee('acme/blog');
});

it('redirects the show route to the draft\'s current step', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
    ]);

    $actor = studioDraftActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.studio.show', $draft))
        ->assertRedirect(route('admin.studio.step.show', [$draft, 1]));
});

it('deletes a draft that has not been generated yet', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
    ]);

    $actor = studioDraftActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.studio.destroy', $draft))
        ->assertRedirect(route('admin.studio.index'));

    expect(ModuleBlueprintDraft::find($draft->id))->toBeNull();
});

it('refuses to delete a draft that has already been generated', function () {
    $module = makeActiveModule('acme/blog');

    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
        'module_id' => $module->id,
        'generated_at' => now(),
    ]);

    $actor = studioDraftActor(['baobab.system.studio.manage']);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.studio.destroy', $draft))
        ->assertRedirect(route('admin.studio.index'));

    expect(ModuleBlueprintDraft::find($draft->id))->not->toBeNull();
});

it('denies deleting a draft without baobab.system.studio.manage', function () {
    $draft = ModuleBlueprintDraft::create([
        'vendor_slug' => 'acme/blog',
        'title' => 'Blog',
        'blueprint' => ['identity' => ['name' => 'acme/blog', 'title' => 'Blog']],
    ]);

    $actor = studioDraftActor([]);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.studio.destroy', $draft))
        ->assertForbidden();
});
