<?php

declare(strict_types=1);

namespace Baobab\Privacy\Contracts;

use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;

/**
 * Deuxième interface du contrat des fournisseurs (spec 16 §2.1, §4.1,
 * décision 6) : de quoi répondre au droit d'accès et à la portabilité. Un
 * fournisseur qui ne l'implémente pas reste légitime (déclaration seule),
 * mais l'archive d'export le signale comme non couvert.
 *
 * `export()` n'est appelé que si `locate()` a répondu oui pour ce sujet.
 */
interface ExportsPersonalData extends PersonalDataProvider
{
    public function export(Subject $subject): PersonalDataExport;
}
