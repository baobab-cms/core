<?php

use Baobab\Branding\Actions\UploadFont;
use Baobab\Branding\Exceptions\InvalidFontUploadException;
use Baobab\Branding\Models\Font;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;

afterEach(function () {
    resetFontsRegistryStorage();
});

function uploadFontActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Font Uploader {$counter}",
        'email' => "font-uploader-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('uploads a valid woff2 and registers it in the registry', function () {
    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);

    $font = app(UploadFont::class)($file, 'Custom Family', 'OFL-1.1', true, uploadFontActor());

    expect($font->family)->toBe('Custom Family')
        ->and($font->source)->toBe(Font::SOURCE_UPLOADED)
        ->and($font->license_attested)->toBeTrue()
        ->and(is_file(storage_path("app/baobab/fonts/{$font->slug}/{$font->files['400']}")))->toBeTrue();
});

it('rejects a file whose real content is not a woff2, regardless of its declared MIME type', function () {
    $path = sys_get_temp_dir().'/baobab-test-fake.woff2';
    file_put_contents($path, 'this is plain text pretending to be a font');
    $file = new UploadedFile($path, 'fake.woff2', 'font/woff2', null, true);

    expect(fn () => app(UploadFont::class)($file, 'Fake Family', null, true, uploadFontActor()))
        ->toThrow(InvalidFontUploadException::class);
});

it('rejects a font larger than the configured limit', function () {
    config(['baobab.fonts.max_upload_size' => 10]);

    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);

    expect(fn () => app(UploadFont::class)($file, 'Custom Family', null, true, uploadFontActor()))
        ->toThrow(InvalidFontUploadException::class);
});

it('requires the license attestation to be accepted', function () {
    $file = new UploadedFile(createTestWoff2(), 'custom.woff2', 'font/woff2', null, true);

    expect(fn () => app(UploadFont::class)($file, 'Custom Family', 'OFL-1.1', false, uploadFontActor()))
        ->toThrow(InvalidFontUploadException::class);
});
