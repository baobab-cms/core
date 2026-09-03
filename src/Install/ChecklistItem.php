<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Une tâche que l'installateur ne peut pas faire lui-même (spec 15 §7).
 *
 * **Ce n'est pas un `Requirement`, et la distinction porte tout le §7.** Un
 * prérequis est un **verdict** — satisfait ou non — constaté avant
 * d'installer, et son remède n'existe que s'il a échoué. Un item de checklist
 * est une **tâche restante**, vraie même quand tout va bien : personne ne peut
 * poser une entrée cron à la place de l'utilisateur, et aucun code ne saura
 * jamais s'il l'a posée. Les deux formes se ressemblent à l'écran et n'ont pas
 * le même sens ; les confondre aurait fait de la checklist une liste de
 * reproches.
 *
 * `command` est ce qui se copie, `files` ce qui se récupère par FTP, `warning`
 * ce qui alerte sans commander. Tous trois sont facultatifs : une tâche peut
 * n'être qu'une phrase — « activez HTTPS » — et c'est très bien.
 *
 * **`files` est une liste de `ChecklistFile` et non un chemin** (Pass C3b2) :
 * un serveur web non identifié fait écrire les *deux* configurations, et le
 * §7 les veut **écrites et affichées en clair** — un chemin seul aurait fait
 * de la moitié de cette promesse une lettre morte.
 */
final readonly class ChecklistItem
{
    /**
     * @param  string  $key  identifiant stable, pour les tests et les traductions
     * @param  string|null  $command  ligne à copier telle quelle, jamais reformatée à l'affichage
     * @param  list<ChecklistFile>  $files  configurations écrites sur disque, avec leur contenu
     * @param  string|null  $caveat  ce que l'on ne sait pas et qu'il faut vérifier
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $body,
        public ?string $command = null,
        public array $files = [],
        public ?string $caveat = null,
        public bool $warning = false,
    ) {}

    /**
     * Une tâche ordinaire : à faire, sans urgence particulière.
     *
     * @param  list<ChecklistFile>  $files
     */
    public static function task(string $key, string $title, string $body, ?string $command = null, array $files = [], ?string $caveat = null): self
    {
        return new self($key, $title, $body, $command, $files, $caveat);
    }

    /**
     * Un avertissement : quelque chose est en place et **ne devrait pas**.
     *
     * Distinct d'une tâche parce que l'écran doit le distinguer : « il reste à
     * faire » et « c'est mal réglé » ne se lisent pas de la même façon, et le
     * second ne doit pas se noyer dans une liste de courses.
     */
    public static function warning(string $key, string $title, string $body, ?string $command = null): self
    {
        return new self($key, $title, $body, $command, [], null, true);
    }
}
