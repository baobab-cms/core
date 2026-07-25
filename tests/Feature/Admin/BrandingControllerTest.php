<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

afterEach(function () {
    foreach (glob(public_path('baobab/tokens-*.css')) ?: [] as $file) {
        File::delete($file);
    }

    resetFontsRegistryStorage();
});

/**
 * @param  list<string>  $permissions
 */
function brandingActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Branding Actor {$counter}",
        'email' => "branding-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the branding screen without baobab.system.branding.manage', function () {
    $actor = brandingActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertForbidden();
});

it('shows the branding screen to an actor with baobab.system.branding.manage', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertOk();
});

it('updates the primary color and audits the change', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => '#112233'])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    expect(BrandingSetting::current()->primary_color)->toBe('#112233');
    expect(AuditEntry::where('action', 'branding.updated')->exists())->toBeTrue();
});

it('rejects an invalid color', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => 'not-a-color'])
        ->assertSessionHasErrors('primary_color');
});

it('writes primary_color into tokens.colors.primary as the single write point', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => '#112233'])
        ->assertRedirect(route('admin.branding.index'));

    $setting = BrandingSetting::current();

    expect($setting->primary_color)->toBe('#112233')
        ->and($setting->tokens['colors']['primary'])->toBe('#112233');
});

it('rejects an unknown tokens group', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['tokens' => ['icons' => ['star' => 'bi-star']]])
        ->assertSessionHasErrors('tokens');
});

it('rejects an unknown key inside a known tokens group', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['tokens' => ['colors' => ['unknown_key' => '#000000']]])
        ->assertSessionHasErrors('tokens');
});

it('rejects the reserved dark tokens group', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['tokens' => ['dark' => []]])
        ->assertSessionHasErrors('tokens');
});

it('triggers a design tokens recompilation on save', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $before = glob(public_path('baobab/tokens-*.css')) ?: [];

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => '#334455'])
        ->assertRedirect(route('admin.branding.index'));

    $after = glob(public_path('baobab/tokens-*.css')) ?: [];

    expect($after)->not->toBe($before)
        ->and(File::get($after[0]))->toContain('#334455');
});

it('applies a brand profile and reflects it in the primary color', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.profile'), ['profile' => 'corporate'])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    $setting = BrandingSetting::current();

    expect($setting->brand_profile)->toBe('corporate')
        ->and($setting->primary_color)->toBe('#1E3A5F');
});

it('flashes an error toast for an unknown profile slug rather than failing', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.profile'), ['profile' => 'does-not-exist'])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');
});

it('hides the fonts section from an actor without baobab.system.fonts.manage', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertOk()
        ->assertDontSee(__('baobab::admin.branding.fonts.upload_title'));
});

it('shows the fonts section to an actor with baobab.system.fonts.manage', function () {
    $actor = brandingActor(['baobab.system.branding.manage', 'baobab.system.fonts.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.branding.fonts.upload_title'));
});

it('denies font upload/deletion routes without baobab.system.fonts.manage', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.fonts.store'), [])
        ->assertForbidden();
});
