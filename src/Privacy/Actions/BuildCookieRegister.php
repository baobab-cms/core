<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Baobab\Privacy\Cookies\CookieDeclaration;
use Baobab\Privacy\Cookies\CookieRegister;
use Baobab\Privacy\Cookies\CoreCookies;

/**
 * Assemble les cookies déclarés (spec 16 §3.2) : ceux du Core, puis la
 * section `privacy.cookies` de chaque module actif — thèmes compris, un
 * thème peut embarquer un traceur. Lecture seule ; la bannière
 * (`<x-baobab::consent-banner />`) et l'écran de consultation (Pass F2)
 * l'appellent, aucun ne recompose sa propre liste (patron
 * `BuildProcessingRegister`).
 *
 * Le manifeste lu est celui stocké à l'installation (`modules.manifest`),
 * déjà validé par le schéma : la catégorie est garantie dans le vocabulaire.
 * Le filtre `baobab.privacy.cookies` reçoit et rend une liste de
 * `CookieDeclaration` — pour qui déclare en PHP plutôt qu'au manifeste ;
 * tout ce qui n'en est pas une est écarté.
 */
final class BuildCookieRegister
{
    public function __construct(private readonly CoreCookies $core) {}

    public function __invoke(): CookieRegister
    {
        $cookies = ($this->core)();

        foreach (Module::query()->where('status', 'active')->orderBy('name')->get() as $module) {
            /** @var list<array{name: string, category: string, purpose: string, duration: string, provider?: string}> $entries */
            $entries = $module->manifest['privacy']['cookies'] ?? [];

            foreach ($entries as $entry) {
                $cookies[] = CookieDeclaration::fromManifest($entry, $module->name);
            }
        }

        /** @var array<mixed> $filtered */
        $filtered = Hook::filter('baobab.privacy.cookies', $cookies);

        return new CookieRegister(array_values(array_filter(
            $filtered,
            static fn (mixed $cookie): bool => $cookie instanceof CookieDeclaration,
        )));
    }
}
