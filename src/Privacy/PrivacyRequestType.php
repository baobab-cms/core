<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/** Le droit exercé par une demande (spec 16 §4). L'effacement passe par un délai de grâce (décision 12). */
enum PrivacyRequestType: string
{
    case Export = 'export';
    case Erasure = 'erasure';
}
