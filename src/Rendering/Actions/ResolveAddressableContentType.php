<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\ContentTypes\Models\ContentType;

/**
 * Résout un Content Type adressable actif par son `url_prefix` (spec 03 §3,
 * spec 02 §4.3) — lecture à la requête (pas au boot) : un Content Type créé
 * ou activé devient routable dès la requête suivante, sans dépendre d'un
 * redémarrage de l'application.
 */
final class ResolveAddressableContentType
{
    public function __invoke(string $prefix): ?ContentType
    {
        return ContentType::where('is_addressable', true)
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->first(fn (ContentType $contentType): bool => $contentType->urlPrefix() === $prefix);
    }
}
