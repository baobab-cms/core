<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Collection;

/**
 * Détecte les médias référencés dans le HTML d'un champ `richtext` (spec 06
 * §5) — cherche les balises <img src="..."> pointant vers un chemin média
 * (`media/{Y}/{m}/{uuid}...`) et résout l'UUID capturé en Media réels, que
 * l'URL pointe vers l'original, une variante de preset ou une édition (seul
 * le préfixe UUID du nom de fichier compte).
 */
final class RichTextMediaUsageScanner
{
    /**
     * @return Collection<int, Media>
     */
    public function scan(string $html): Collection
    {
        if (! preg_match_all('/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $imgMatches)) {
            return new Collection;
        }

        $uuids = [];

        foreach ($imgMatches[1] as $src) {
            if (preg_match('#/media/\d{4}/\d{2}/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})#i', $src, $uuidMatch)) {
                $uuids[] = strtolower($uuidMatch[1]);
            }
        }

        if ($uuids === []) {
            return new Collection;
        }

        return Media::whereIn('uuid', array_unique($uuids))->get();
    }
}
