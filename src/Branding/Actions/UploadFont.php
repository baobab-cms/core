<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Exceptions\InvalidFontUploadException;
use Baobab\Branding\Models\Font;
use Baobab\Branding\Support\PublishFontAssets;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Upload d'une police au registre (spec 18 §5.4). Valide le contenu réel
 * (magic bytes woff2, jamais la seule extension/MIME déclaré — patron
 * `UploadMedia::assertMimeTypeAllowed`, appliqué ici au format binaire du
 * fichier plutôt qu'à son type MIME), la taille, et exige l'attestation de
 * licence — la responsabilité légale est contractuellement transférée à
 * l'utilisateur (spec §5.4), jamais vérifiée techniquement.
 *
 * Simplification assumée (registre v1) : un fichier uploadé n'est jamais
 * traité comme variable (aucune introspection des axes) — enregistré sous
 * la clé de poids `400`, cohérent avec l'absence de sélecteur de graisse
 * dans le formulaire d'upload.
 */
final class UploadFont
{
    private const string MAGIC_BYTES = 'wOF2';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublishFontAssets $publish,
    ) {}

    public function __invoke(
        UploadedFile $file,
        string $family,
        ?string $license,
        bool $licenseAttested,
        User $actor,
        ?UploadedFile $licenseFile = null,
    ): Font {
        $this->assertMagicBytes($file);
        $this->assertSizeAllowed((int) $file->getSize());

        if (! $licenseAttested) {
            throw InvalidFontUploadException::licenseAttestationRequired();
        }

        $slug = $this->uniqueSlug($family);
        $directory = storage_path("app/baobab/fonts/{$slug}");
        File::ensureDirectoryExists($directory);

        $fileName = "{$slug}.woff2";
        File::copy((string) $file->getRealPath(), "{$directory}/{$fileName}");

        $licenseFileName = null;

        if ($licenseFile !== null) {
            $licenseFileName = 'LICENSE'.($licenseFile->extension() !== '' ? '.'.$licenseFile->extension() : '');
            File::copy((string) $licenseFile->getRealPath(), "{$directory}/{$licenseFileName}");
        }

        $font = Font::create([
            'family' => $family,
            'slug' => $slug,
            'source' => Font::SOURCE_UPLOADED,
            'is_variable' => false,
            'axes' => null,
            'files' => ['400' => $fileName],
            'license' => $license,
            'license_file' => $licenseFileName,
            'license_attested' => true,
            'theme_module_id' => null,
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->record('font.uploaded', $font, ['family' => $family]);

        ($this->publish)();

        Hook::action('baobab.fonts.registered', $font);

        return $font;
    }

    private function assertMagicBytes(UploadedFile $file): void
    {
        $handle = fopen((string) $file->getRealPath(), 'rb');

        if ($handle === false) {
            throw InvalidFontUploadException::invalidMagicBytes();
        }

        $header = fread($handle, 4);
        fclose($handle);

        if ($header !== self::MAGIC_BYTES) {
            throw InvalidFontUploadException::invalidMagicBytes();
        }
    }

    private function assertSizeAllowed(int $sizeInBytes): void
    {
        $defaultMax = (int) config('baobab.fonts.max_upload_size', 2_097_152);

        /** @var int $max */
        $max = Hook::filter('baobab.fonts.uploading.max_size', $defaultMax);

        if ($sizeInBytes > $max) {
            throw InvalidFontUploadException::tooLarge($sizeInBytes, $max);
        }
    }

    private function uniqueSlug(string $family): string
    {
        $base = Str::slug($family);
        $slug = $base;
        $suffix = 1;

        while (Font::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
