<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Ce qu'une installation terminée a produit (spec 15 §4, §7).
 *
 * Le profil rendu est celui **constaté à la finalisation**, pas celui détecté
 * à l'étape 1 : `symlink()` peut exister et échouer quand même. C'est ce
 * profil-là que la checklist du §7 doit lire, sans quoi elle proposerait une
 * manœuvre que l'hébergement vient de refuser.
 */
final readonly class InstallationSummary
{
    /**
     * @param  list<string>  $skipped  étapes déjà passées, non rejouées
     */
    public function __construct(
        public HostingProfile $profile,
        public RequirementsReport $requirements,
        public DatabaseInspection $database,
        public ?SuperAdminResult $superAdmin,
        public array $skipped = [],
    ) {}

    public function wasResumed(): bool
    {
        return $this->skipped !== [];
    }
}
