<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Serveur web servant l'instance (spec 15 §4.1, §7).
 *
 * Il gouverne la checklist de fin : configuration Nginx générée d'un côté,
 * confirmation du `.htaccess` déjà embarqué dans l'archive de l'autre. D'où
 * `Unknown`, qui n'est pas un défaut de détection mais l'état réel d'une
 * installation menée en ligne de commande : `SERVER_SOFTWARE` n'existe que
 * dans une requête HTTP.
 */
enum WebServer: string
{
    case Apache = 'apache';
    case Nginx = 'nginx';
    case Other = 'other';
    case Unknown = 'unknown';

    public static function detect(?string $serverSoftware): self
    {
        if ($serverSoftware === null || $serverSoftware === '') {
            return self::Unknown;
        }

        $haystack = mb_strtolower($serverSoftware);

        return match (true) {
            str_contains($haystack, 'apache') => self::Apache,
            str_contains($haystack, 'nginx') => self::Nginx,
            default => self::Other,
        };
    }
}
