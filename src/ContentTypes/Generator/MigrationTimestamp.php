<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

/**
 * `Y_m_d_His` seul peut entrer en collision entre deux migrations générées
 * dans la même seconde (tests, imports en rafale...) — Laravel traiterait
 * alors la seconde comme « déjà exécutée ». Un suffixe aléatoire élimine la
 * collision sans changer la convention de tri chronologique du nom de fichier.
 */
final class MigrationTimestamp
{
    public static function generate(): string
    {
        return now()->format('Y_m_d_His').'_'.substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
