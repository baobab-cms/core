<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/** La stratégie qu'un fournisseur a retenue pour un sujet (spec 16 §4.3). */
enum EraseOutcome: string
{
    case Deleted = 'deleted';
    case Anonymized = 'anonymized';
    case Retained = 'retained';
}
