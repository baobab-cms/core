<?php

declare(strict_types=1);

namespace Baobab\Mail;

/**
 * Les trois états d'une ligne de journal (spec 13 §4.1 : `queued` → `sent` |
 * `failed`). Catalogue **fermé** — la spec n'en fait pas un point d'extension
 * — d'où un enum plutôt qu'une chaîne libre, patron `RelationType`.
 *
 * `Queued` n'est pas un état transitoire de confort : un e-mail part toujours
 * en queue (§2.2), si bien qu'une ligne qui y reste signale un worker arrêté.
 * C'est la seule information que le journal donne sur l'infrastructure, et
 * elle vaut d'être lisible telle quelle.
 */
enum MailLogStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
