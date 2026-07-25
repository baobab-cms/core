<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Branding\Models\Font;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;

afterEach(function () {
    resetFontsRegistryStorage();
});

function fontsActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fonts Actor {$counter}",
        'email' => "fonts-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.branding.manage');
    app(GrantPermission::class)($user, 'baobab.system.fonts.manage');

    return $user;
}

it('uploads a font through the admin screen', function () {
    $actor = fontsActor();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.fonts.store'), [
            'file' => new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true),
            'family' => 'Custom Family',
            'license' => 'OFL-1.1',
            'license_attested' => '1',
        ])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    expect(Font::where('family', 'Custom Family')->exists())->toBeTrue();
});

it('rejects an upload missing the license attestation', function () {
    $actor = fontsActor();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.fonts.store'), [
            'file' => new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true),
            'family' => 'Custom Family',
        ])
        ->assertSessionHasErrors('license_attested');
});

it('deletes an unused font through the admin screen', function () {
    $actor = fontsActor();

    $this->actingAs($actor, 'baobab')->post(route('admin.branding.fonts.store'), [
        'file' => new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true),
        'family' => 'Deletable Family',
        'license' => 'OFL-1.1',
        'license_attested' => '1',
    ]);

    $font = Font::where('family', 'Deletable Family')->firstOrFail();

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.branding.fonts.destroy', $font))
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    expect(Font::find($font->id))->toBeNull();
});

it('flashes an error toast rather than failing when deleting a bundled font', function () {
    $actor = fontsActor();
    $font = Font::where('source', Font::SOURCE_BUNDLED)->firstOrFail();

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.branding.fonts.destroy', $font))
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    expect(Font::find($font->id))->not->toBeNull();
});
