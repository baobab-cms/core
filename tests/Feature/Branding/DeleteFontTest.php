<?php

use Baobab\Branding\Actions\DeleteFont;
use Baobab\Branding\Actions\UploadFont;
use Baobab\Branding\Exceptions\FontInUseException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Models\Font;
use Baobab\Modules\Models\Module;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

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

it('deletes the font row even when its file directory was already removed from disk', function () {
    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);
    $font = app(UploadFont::class)($file, 'Already Gone Family', 'OFL-1.1', true, deleteFontActor());
    File::deleteDirectory(storage_path("app/baobab/fonts/{$font->slug}"));

    app(DeleteFont::class)($font);

    expect(Font::find($font->id))->toBeNull();
});

it('reports the "theme" level when the font is only referenced by the active theme\'s tokens', function () {
    Module::create([
        'name' => 'acme/theme',
        'title' => 'Acme Theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-theme',
        'status' => 'active',
        'manifest' => ['tokens' => ['fonts' => ['body' => 'Theme Family']]],
    ]);

    $file = new UploadedFile(createTestWoff2(), 'theme.woff2', 'font/woff2', null, true);
    $font = app(UploadFont::class)($file, 'Theme Family', 'OFL-1.1', true, deleteFontActor());

    try {
        app(DeleteFont::class)($font);
        $this->fail('FontInUseException attendue.');
    } catch (FontInUseException $e) {
        expect($e->level)->toBe('theme')
            ->and($e->slot)->toBe('body');
    }

    expect(Font::find($font->id))->not->toBeNull();
});

it('reports the "core" level when the font is only referenced by the Core defaults', function () {
    $file = new UploadedFile(createTestWoff2(), 'figtree.woff2', 'font/woff2', null, true);
    $font = app(UploadFont::class)($file, 'Figtree Variable', 'OFL-1.1', true, deleteFontActor());

    try {
        app(DeleteFont::class)($font);
        $this->fail('FontInUseException attendue.');
    } catch (FontInUseException $e) {
        expect($e->level)->toBe('core')
            ->and($e->slot)->toBe('body');
    }

    expect(Font::find($font->id))->not->toBeNull();
});
