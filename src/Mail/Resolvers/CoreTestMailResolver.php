<?php

declare(strict_types=1);

namespace Baobab\Mail\Resolvers;

use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Models\MailLogEntry;

/**
 * Rend `core.test` renvoyable depuis le journal (spec 13 §4.2).
 *
 * **Le seul template du Core qui puisse l'être**, et ce n'est pas un hasard :
 * le resolver ne reçoit que ce que le journal conserve (suivi n° 195), or les
 * trois `core.content.review_*` dépendent d'un contenu précis et
 * `core.security.impersonation_started` du nom d'un administrateur — aucun des
 * deux n'est mémorisé. `core.test` n'a qu'une variable, l'heure d'envoi, qui
 * est recalculable par définition.
 *
 * L'usage est réel plutôt que démonstratif : quand un transport a été réparé,
 * on relance le dernier test depuis le journal pour vérifier qu'il passe,
 * plutôt que de retourner composer un envoi dans l'écran des templates.
 *
 * **L'heure rendue est celle du renvoi, jamais celle de l'envoi d'origine**,
 * et c'est tout le principe du §4.2 : le renvoi re-rend depuis l'état courant.
 * Un e-mail de test qui annoncerait l'heure d'un envoi vieux de trois jours
 * mentirait sur ce qu'il vient de prouver.
 */
final class CoreTestMailResolver implements MailDataResolver
{
    public function resolve(MailLogEntry $entry): array
    {
        return ['sent_at' => now()->format('d/m/Y H:i')];
    }
}
