<?php

declare(strict_types=1);

namespace Baobab\Auth;

/**
 * Durée du cookie « se souvenir de moi » de l'admin (spec 04 §9,
 * décision 6) — point unique de résolution, patron `ModuleUploadPaths`
 * (suivi n° 119) : un `config/baobab.php` publié avant l'ajout de
 * `auth.remember_days` écrase tout le bloc `auth` (fusion superficielle de
 * `mergeConfigFrom`), la clé est alors absente. Absente, vide ou inférieure
 * à un jour, elle retombe sur le défaut du package — jamais zéro.
 */
final class RememberDuration
{
    public const int DEFAULT_DAYS = 30;

    public static function days(): int
    {
        $days = config('baobab.auth.remember_days');

        return is_numeric($days) && (int) $days >= 1 ? (int) $days : self::DEFAULT_DAYS;
    }

    public static function minutes(): int
    {
        return self::days() * 1440;
    }
}
