<?php

declare(strict_types=1);

namespace Baobab\Users;

/**
 * Ce que produit la confirmation d'un lien : soit la demande passe à la
 * nouvelle adresse (l'ancienne vient de valider), soit elle est appliquée.
 */
enum ProfileChangeOutcome: string
{
    case AwaitingNewAddress = 'awaiting_new_address';
    case Applied = 'applied';
}
