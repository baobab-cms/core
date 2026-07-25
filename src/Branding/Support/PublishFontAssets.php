<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

use Baobab\Branding\Models\Font;
use Illuminate\Support\Facades\File;

/**
 * Publie le registre de polices (spec 18 §5.2) : jonction **unique**
 * `public/baobab/fonts` → `storage/app/baobab/fonts` (à la différence de
 * `PublishThemeAssets`, une jonction par thème — ici tout le registre,
 * quelle que soit sa source, partage un seul point de publication), patron
 * exact `PublishThemeAssets` (`Filesystem::link()`, `clearstatcache()` après
 * création — piège Windows déjà documenté là-bas). Idempotente : ne recrée
 * pas un lien déjà en place.
 *
 * Auto-répare aussi le contenu : une police `source=bundled` dont les
 * fichiers ne sont pas encore présents sous `storage/app/baobab/fonts/{slug}/`
 * (premier boot, ou disque purgé) est copiée depuis les fichiers vendored du
 * package (`ressources/fonts/{slug}/`) — jamais une dépendance réseau,
 * jamais un import Vite (décision D6, spec 18 §12).
 */
final class PublishFontAssets
{
    private const string STORAGE_DIRECTORY = 'baobab/fonts';

    public function __invoke(): void
    {
        $this->ensureLink();
        $this->ensureBundledFilesPresent();
    }

    private function ensureLink(): void
    {
        $link = public_path(self::STORAGE_DIRECTORY);

        if (File::exists($link)) {
            return;
        }

        $target = storage_path('app/'.self::STORAGE_DIRECTORY);
        File::ensureDirectoryExists($target);
        File::ensureDirectoryExists(dirname($link));

        File::link($target, $link);
        clearstatcache(true, $link);
    }

    private function ensureBundledFilesPresent(): void
    {
        $vendored = dirname(__DIR__, 3).'/ressources/fonts';

        /** @var iterable<Font> $bundled */
        $bundled = Font::query()->where('source', Font::SOURCE_BUNDLED)->get();

        foreach ($bundled as $font) {
            $source = "{$vendored}/{$font->slug}";
            $destination = storage_path('app/'.self::STORAGE_DIRECTORY."/{$font->slug}");

            if (! File::isDirectory($source)) {
                continue;
            }

            File::ensureDirectoryExists($destination);

            foreach (File::files($source) as $file) {
                $target = "{$destination}/{$file->getFilename()}";

                if (! File::exists($target)) {
                    File::copy($file->getPathname(), $target);
                }
            }
        }
    }
}
