<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

/**
 * Ce qui existait **avant** que la génération n'écrive quoi que ce soit, et
 * que sa compensation n'a donc pas le droit de détruire (suivi n° 148).
 *
 * Ces deux témoins ne sont pas une précaution de principe. Le défaut d'origine
 * — celui du 14 août 2026 — se déclenchait précisément sur un **nom déjà
 * pris** : la seconde soumission heurtait `modules_name_unique`. Une
 * compensation qui se contenterait de constater « il y a une ligne `modules`,
 * je la désinstalle » retirerait alors le module de quelqu'un d'autre, avec
 * ses tables, ses données et ses fichiers. Elle serait plus destructrice que
 * le défaut qu'elle répare.
 *
 * La règle : on ne défait que ce qu'on a fait. Ce qu'on a trouvé sur place
 * reste sur place, y compris quand c'est précisément la cause de l'échec.
 */
final readonly class GenerationPreexistingState
{
    public function __construct(
        public bool $moduleRow,
        public bool $moduleDirectory,
    ) {}
}
