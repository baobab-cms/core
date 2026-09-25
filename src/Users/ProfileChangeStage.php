<?php

declare(strict_types=1);

namespace Baobab\Users;

/**
 * Où en est une demande de changement de profil (spec 05 §5, décision 5 j) :
 * en attente de l'adresse actuelle, ou de la nouvelle. Le chemin « boîte
 * perdue » (g) naît directement à l'étape de la nouvelle adresse.
 */
enum ProfileChangeStage: string
{
    case Verify = 'verify';
    case Confirm = 'confirm';
}
