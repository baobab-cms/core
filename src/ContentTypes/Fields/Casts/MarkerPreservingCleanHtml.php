<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Remplace `Mews\Purifier\Casts\CleanHtml` (spec 02 §3.2) pour tout champ
 * `richtext` : identique en tout point (même liste blanche, mêmes deux sens
 * `get()`/`set()`), sauf qu'un marqueur `<span data-baobab-embed="...">`
 * traverse la purification intact au lieu d'être supprimé — aucun élément de
 * ce nom n'existe dans `HTML.Allowed` (`BaobabServiceProvider::configurePurifier()`),
 * et l'y ajouter aurait élargi cette liste blanche pour un seul consommateur.
 * Le marqueur est retiré avant `clean()`, réinjecté tel quel après : la
 * garantie de sécurité reste entièrement portée par Purifier sur tout le
 * reste du contenu, jamais affaiblie pour ce cas particulier (M8 point 6,
 * Pass C4, suivi n° 270).
 *
 * Générique par construction : cette classe ignore ce qu'un marqueur
 * contient (`form:{slug}` aujourd'hui, éventuellement autre chose demain) —
 * ContentTypes n'a donc jamais besoin de connaître le domaine Forms. La
 * résolution du marqueur en formulaire réellement rendu est un problème
 * distinct, traité à l'affichage via le filtre `baobab.richtext.display`
 * (`field.richtext-display.blade.php`), jamais ici.
 *
 * @implements CastsAttributes<string, string>
 */
final class MarkerPreservingCleanHtml implements CastsAttributes
{
    private const MARKER_PATTERN = '/<span[^>]*\sdata-baobab-embed="[^"]*"[^>]*>.*?<\/span>/su';

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->cleanPreservingMarkers($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->cleanPreservingMarkers($value);
    }

    private function cleanPreservingMarkers(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $markers = [];

        $withoutMarkers = preg_replace_callback(self::MARKER_PATTERN, function (array $match) use (&$markers): string {
            $token = 'baobabembedmarker'.count($markers).'token';
            $markers[$token] = $match[0];

            return $token;
        }, $value);

        $cleaned = (string) clean($withoutMarkers ?? $value);

        return $markers === [] ? $cleaned : strtr($cleaned, $markers);
    }
}
