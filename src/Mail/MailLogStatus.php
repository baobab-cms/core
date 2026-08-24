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

    /**
     * Ajouté en Pass B2 avec l'écran qui le consomme, et non en B1 où aucune
     * clé de traduction n'existait encore. Il est **ici** et non dans le
     * contrôleur parce que deux surfaces en ont besoin — la colonne du tableau
     * et les options du filtre — et qu'un libellé écrit deux fois finit par
     * diverger une fois.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => __('baobab::admin.mail_log.status_queued'),
            self::Sent => __('baobab::admin.mail_log.status_sent'),
            self::Failed => __('baobab::admin.mail_log.status_failed'),
        };
    }

    /**
     * La variante de pastille de cet état. Elle est ici et non dans la vue
     * pour la raison même qui a sorti la table des variantes de `<x-badge>`
     * en août 2026 (suivi n° 138) : associer un état à un rôle visuel est une
     * décision, pas de l'affichage — et une vue admin ne porte pas de logique.
     *
     * `Queued` prend `warning` plutôt que `neutral` : rester en file est
     * normal le temps qu'un worker passe, mais y **demeurer** signale un
     * worker arrêté. C'est la seule chose que ce journal dise de
     * l'infrastructure, et elle mérite d'accrocher l'œil.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Queued => 'warning',
        };
    }
}
