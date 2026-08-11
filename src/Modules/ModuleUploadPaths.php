<?php

declare(strict_types=1);

namespace Baobab\Modules;

/**
 * Résolution des racines d'écriture et de la taille maximale d'un upload de
 * module (M8 point 9, Pass B).
 *
 * **Pourquoi cette classe plutôt que des `config()` dispersés.** `mergeConfigFrom`
 * ne fusionne que les clés de **premier niveau** : une application qui a publié
 * son `config/baobab.php` avant l'ajout du bloc `modules.upload` définit déjà
 * `modules`, donc sa version écrase entièrement celle du package et le
 * sous-bloc `upload` n'existe pas à l'exécution. Publier ce fichier étant la
 * façon documentée de personnaliser les chemins, le cas est la règle, pas
 * l'exception.
 *
 * Défaut relevé le 10 août 2026 en vérification navigateur, avec trois
 * conséquences dont deux graves :
 *
 * - `max_size` absent → la règle de validation devenait `max:0`, tout fichier
 *   refusé (symptôme visible, bénin) ;
 * - racine absente → `(string) null` = `''`, et la cible d'extraction devenait
 *   `/{slug}`, à la racine du disque ;
 * - racine absente → `realpath('')` retourne le **répertoire de travail
 *   courant**, si bien que le garde-fou de suppression de fichiers, censé
 *   borner l'effacement aux racines configurées, autorisait tout ce qui vit
 *   sous le dossier du projet.
 *
 * D'où la règle tenue ici : une valeur absente **retombe sur le défaut du
 * package**, jamais sur une chaîne vide, et `roots()` ne rend que des chemins
 * utilisables.
 */
final class ModuleUploadPaths
{
    private const int DEFAULT_MAX_SIZE = 20 * 1024 * 1024;

    public static function modules(): string
    {
        return self::path('modules_path', 'modules');
    }

    public static function themes(): string
    {
        return self::path('themes_path', 'themes');
    }

    public static function maxSize(): int
    {
        $configured = config('baobab.modules.upload.max_size');

        return is_numeric($configured) && (int) $configured > 0
            ? (int) $configured
            : self::DEFAULT_MAX_SIZE;
    }

    /**
     * Racines hors desquelles rien n'est jamais supprimé.
     *
     * @return list<string>
     */
    public static function roots(): array
    {
        return array_values(array_unique([self::modules(), self::themes()]));
    }

    private static function path(string $key, string $default): string
    {
        $configured = config("baobab.modules.upload.{$key}");

        $path = is_string($configured) ? trim($configured) : '';

        return rtrim($path !== '' ? $path : base_path($default), '/\\');
    }
}
