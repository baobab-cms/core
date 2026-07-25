<?php

use Baobab\Branding\Actions\DeleteFont;
use Baobab\Branding\Actions\UploadFont;
use Baobab\Branding\Exceptions\FontInUseException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Models\Font;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

afterEach(function () {
    resetFontsRegistryStorage();
});

function deleteFontActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Delete Font Actor {$counter}",
        'email' => "delete-font-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('refuses to delete a bundled font', function () {
    $font = Font::where('source', Font::SOURCE_BUNDLED)->firstOrFail();

    expect(fn () => app(DeleteFont::class)($font))->toThrow(RuntimeException::class);

    expect(Font::find($font->id))->not->toBeNull();
});

it('refuses to delete an uploaded font referenced by the current admin token override', function () {
    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);
    $font = app(UploadFont::class)($file, 'Custom Family', 'OFL-1.1', true, deleteFontActor());

    BrandingSetting::create(['tokens' => ['fonts' => ['body' => 'Custom Family']]]);

    expect(fn () => app(DeleteFont::class)($font))->toThrow(FontInUseException::class);

    expect(Font::find($font->id))->not->toBeNull();
});

it('deletes an unused uploaded font, removing its files from disk', function () {
    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);
    $font = app(UploadFont::class)($file, 'Unused Family', 'OFL-1.1', true, deleteFontActor());
    $directory = storage_path("app/baobab/fonts/{$font->slug}");

    expect(is_dir($directory))->toBeTrue();

    app(DeleteFont::class)($font);

    expect(Font::find($font->id))->toBeNull()
        ->and(is_dir($directory))->toBeFalse();
});
