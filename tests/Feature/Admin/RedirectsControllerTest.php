<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Seo\Models\Redirect;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * @param  list<string>  $permissions
 */
function redirectsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Redirects actor {$counter}",
        'email' => "redirects-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies access without baobab.system.redirects.manage', function () {
    $user = redirectsActor([]);

    $this->actingAs($user, 'baobab')->get(route('admin.redirects.index'))->assertForbidden();
});

it('creates a redirect from the admin form', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.redirects.store'), [
            'source' => '/old',
            'target' => '/new',
            'status_code' => 301,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.redirects.index'));

    $redirect = Redirect::where('source', '/old')->first();

    expect($redirect)->not->toBeNull()
        ->and($redirect->target)->toBe('/new')
        ->and($redirect->source_kind)->toBe('manual');
});

it('rejects a source that is not unique', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);
    Redirect::create(['source' => '/dup', 'target' => '/a', 'status_code' => 301]);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.redirects.store'), ['source' => '/dup', 'target' => '/b', 'status_code' => 301])
        ->assertSessionHasErrors('source');
});

it('updates a redirect', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);
    $redirect = Redirect::create(['source' => '/old', 'target' => '/new', 'status_code' => 301]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.redirects.update', ['redirect' => $redirect->id]), [
            'source' => '/old',
            'target' => '/updated',
            'status_code' => 302,
        ])
        ->assertRedirect(route('admin.redirects.index'));

    expect($redirect->fresh()?->target)->toBe('/updated')
        ->and($redirect->fresh()?->status_code)->toBe(302);
});

it('deletes a redirect', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);
    $redirect = Redirect::create(['source' => '/old', 'target' => '/new', 'status_code' => 301]);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.redirects.destroy', ['redirect' => $redirect->id]))
        ->assertRedirect(route('admin.redirects.index'));

    expect(Redirect::find($redirect->id))->toBeNull();
});

it('exports redirects as CSV', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);
    Redirect::create(['source' => '/old', 'target' => '/new', 'status_code' => 301]);

    $response = $this->actingAs($user, 'baobab')->get(route('admin.redirects.export'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->getContent())->toContain('/old')->toContain('/new');
});

it('imports redirects from a CSV file, upserting by source', function () {
    $user = redirectsActor(['baobab.system.redirects.manage']);
    Redirect::create(['source' => '/existing', 'target' => '/old-target', 'status_code' => 301]);

    $csv = "source,target,status_code,is_active\n"
        ."/existing,/new-target,301,1\n"
        ."/brand-new,/brand-new-target,302,1\n"
        .",,301,1\n";

    $file = UploadedFile::fake()->createWithContent('redirects.csv', $csv);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.redirects.import'), ['file' => $file])
        ->assertRedirect(route('admin.redirects.index'));

    expect(Redirect::where('source', '/existing')->first()?->target)->toBe('/new-target')
        ->and(Redirect::where('source', '/brand-new')->first()?->target)->toBe('/brand-new-target')
        ->and(Redirect::query()->count())->toBe(2);
});
