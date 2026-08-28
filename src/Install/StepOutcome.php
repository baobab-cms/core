<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Ce qu'une étape d'installation a produit — suivi n° 224.
 *
 * Elle existe pour que le pipeline puisse être conduit **une étape à la fois**
 * (spec 15 §6.1) sans que le web ait à connaître la séquence. La console
 * enchaîne les étapes et accumule ces résultats ; le navigateur en demande une
 * par requête et rend celui-ci.
 *
 * `$details` est destiné à l'affichage : les migrations réellement passées,
 * aujourd'hui, et rien d'autre. Il est **toujours sûr à montrer** — aucun
 * secret n'y entre, et c'est une propriété à préserver si on l'enrichit.
 */
final readonly class StepOutcome
{
    /**
     * @param  list<string>  $details  lignes affichables produites par l'étape
     */
    public function __construct(
        public string $step,
        public string $label,
        public array $details = [],
        public ?DatabaseInspection $inspection = null,
        public ?string $hashDriver = null,
        public ?SuperAdminResult $superAdmin = null,
        public ?HostingProfile $profile = null,
    ) {}
}
