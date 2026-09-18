<?php

declare(strict_types=1);

namespace Baobab\Privacy\Contracts;

use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;

/**
 * Socle du contrat des détenteurs de données personnelles (spec 16 §2.1,
 * décision 6) : de quoi figurer au registre des traitements et répondre à
 * « ce sujet a-t-il des données ici ? ». L'export (Pass B) et l'effacement
 * (Pass C) s'y ajoutent par deux interfaces distinctes, jamais par des
 * méthodes factices.
 */
interface PersonalDataProvider
{
    /** Identifiant stable et unique, `domaine.objet` (ex. `forms.submissions`). */
    public function key(): string;

    public function describe(): DataDeclaration;

    public function locate(Subject $subject): bool;
}
