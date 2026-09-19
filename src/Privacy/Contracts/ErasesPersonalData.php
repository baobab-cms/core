<?php

declare(strict_types=1);

namespace Baobab\Privacy\Contracts;

use Baobab\Privacy\EraseReport;
use Baobab\Privacy\Subject;

/**
 * Troisième interface du contrat des fournisseurs (spec 16 §2.1, §4.3,
 * décision 6) : de quoi répondre au droit à l'effacement. Le fournisseur
 * choisit la stratégie (suppression, anonymisation, conservation) et la
 * documente dans son rapport ; il n'est appelé que si `locate()` a répondu
 * oui, avec un sujet déjà résolu en identité complète (compte et e-mail).
 */
interface ErasesPersonalData extends PersonalDataProvider
{
    public function erase(Subject $subject): EraseReport;
}
