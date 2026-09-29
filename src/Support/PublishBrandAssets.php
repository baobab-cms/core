<?php

declare(strict_types=1);

namespace Baobab\Support;

use Illuminate\Support\Facades\File;

/**
 * Publie l'icône de marque Baobab (le produit), fixe et non
 * tenant-configurable — à ne pas confondre avec le favicon/logo du branding
 * par site (`BrandingSetting`, spec 18). Patron `PublishConsentAssets`
 * (`Filesystem::link()`, `clearstatcache()` après création — piège Windows
 * documenté là-bas). Idempotente et auto-réparatrice, aucune copie : le
 * fichier servi est celui du package, une mise à jour du Core l'emporte sans
 * republier. Le cache navigateur est invalidé par l'empreinte du contenu
 * (`url()`), pas par un nom de fichier versionné.
 */
final class PublishBrandAssets
{
    private const string PUBLIC_DIRECTORY = 'baobab/images';

    private const string ICON = 'baobab-icon.png';

    public function __invoke(): string
    {
        $link = public_path(self::PUBLIC_DIRECTORY);

        if (! File::exists($link)) {
            File::ensureDirectoryExists(dirname($link));
            File::link(self::sourceDirectory(), $link);
            clearstatcache(true, $link);
        }

        return $this->url();
    }

    public function url(): string
    {
        $version = substr((string) md5_file(self::sourceDirectory().'/'.self::ICON), 0, 10);

        return '/'.self::PUBLIC_DIRECTORY.'/'.self::ICON.'?v='.$version;
    }

    public static function sourceDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/images';
    }
}
