<?php

declare(strict_types=1);

namespace Baobab\Forms;

/**
 * Les quatre états d'une soumission (spec 14 §6.1). Catalogue fermé, patron
 * `Baobab\Mail\MailLogStatus`. `label()`/`badgeVariant()` n'existent pas
 * encore : aucun écran ne les consomme avant la Pass B (même leçon que
 * `MailLogStatus`, ajoutés avec l'écran qui les consomme).
 */
enum FormSubmissionStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Spam = 'spam';
    case Archived = 'archived';
}
