<?php

declare(strict_types=1);

namespace Baobab\Scheduler\Support;

/**
 * Formateur cron → français (spec 12 §2.3 : « tous les jours à 03:00 »).
 * Couvre les formes réellement utilisées par les tâches Core et les modules
 * de test (chaque minute, quotidien à heure fixe, hebdomadaire à jour+heure
 * fixes) ; toute autre forme retombe sur l'expression brute plutôt que sur
 * une description erronée — aucune dépendance ajoutée pour ça (suivi de la
 * décision prise avec l'utilisateur, aucun traducteur cron→langage
 * disponible même en transitif).
 */
final class CronHumanizer
{
    public static function describe(string $cron): string
    {
        $fields = preg_split('/\s+/', trim($cron));

        if ($fields === false || count($fields) !== 5) {
            return $cron;
        }

        [$minute, $hour, $day, $month, $weekday] = $fields;

        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Chaque minute';
        }

        if (! self::isFixedTime($minute, $hour)) {
            return $cron;
        }

        if ($day === '*' && $month === '*' && $weekday === '*') {
            return sprintf('Tous les jours à %s', self::formatTime($minute, $hour));
        }

        if ($day === '*' && $month === '*' && ctype_digit($weekday)) {
            return sprintf('Chaque %s à %s', self::weekdayLabel((int) $weekday), self::formatTime($minute, $hour));
        }

        return $cron;
    }

    private static function isFixedTime(string $minute, string $hour): bool
    {
        return ctype_digit($minute) && ctype_digit($hour);
    }

    private static function formatTime(string $minute, string $hour): string
    {
        return sprintf('%02d:%02d', (int) $hour, (int) $minute);
    }

    private static function weekdayLabel(int $weekday): string
    {
        return match (abs($weekday) % 7) {
            0 => 'dimanche',
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
        };
    }
}
