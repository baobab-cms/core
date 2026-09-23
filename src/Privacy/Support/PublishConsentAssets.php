<?php

declare(strict_types=1);

namespace Baobab\Privacy\Support;

use Illuminate\Support\Facades\File;

/**
 * Publie le script de consentement (spec 16 §3.2, décision 19) : jonction
 * `public/baobab/consent` → `resources/consent` du package, patron
 * `PublishFontAssets`/`PublishThemeAssets` (`Filesystem::link()`,
 * `clearstatcache()` après création — piège Windows documenté là-bas).
 * Idempotente et auto-réparatrice : appelée par le composant au moment où
 * il émet le script, un lien effacé se rétablit à la requête suivante.
 *
 * Aucune copie : le fichier servi est celui du package, donc une mise à
 * jour du Core l'emporte sans republier. Le cache navigateur est invalidé
 * par l'empreinte du contenu (`url()`), pas par un nom de fichier versionné.
 */
final class PublishConsentAssets
{
    private const string PUBLIC_DIRECTORY = 'baobab/consent';

    private const string SCRIPT = 'consent.js';

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
        $version = substr((string) md5_file(self::sourceDirectory().'/'.self::SCRIPT), 0, 10);

        return '/'.self::PUBLIC_DIRECTORY.'/'.self::SCRIPT.'?v='.$version;
    }

    public static function sourceDirectory(): string
    {
        return dirname(__DIR__, 3).'/resources/consent';
    }
}
