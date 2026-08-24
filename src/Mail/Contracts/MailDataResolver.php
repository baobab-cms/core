<?php

declare(strict_types=1);

namespace Baobab\Mail\Contracts;

use Baobab\Mail\Models\MailLogEntry;

/**
 * Ce qui rend un template **renvoyable** (spec 13 §4.2) : la capacité de
 * re-produire les données du rendu **depuis l'état courant**. Un module en
 * déclare un par la clé `resolver` de son bloc `mails` (§3.1) ; sans lui, le
 * bouton « Renvoyer » du journal reste désactivé avec son explication.
 *
 * **La divergence avec `RedeliverWebhookDelivery` est volontaire, ne pas
 * l'aligner.** Une re-livraison de webhook rejoue un payload figé en base, au
 * motif explicite que la source a pu changer depuis. Ici, c'est l'inverse qui
 * est demandé : « aucune donnée de rendu n'est sérialisée en base à cette fin
 * — le renvoi re-rend depuis l'état actuel, jamais depuis une copie figée ».
 * Un e-mail est adressé à quelqu'un ; lui réexpédier un lien périmé ou un
 * contenu qui a changé depuis serait plus nuisible que de ne rien envoyer.
 *
 * D'où le `null` : la source peut avoir disparu entre l'envoi et le renvoi.
 * Le resolver le dit, et le renvoi est refusé avec sa raison plutôt que de
 * produire un e-mail à trous.
 *
 * **Ce que le resolver reçoit borne ce qu'il peut faire.** Le journal ne
 * conserve que la clé de template, le destinataire et l'horodatage : sont donc
 * re-calculables les e-mails dérivables de leur seul destinataire — invitation,
 * réinitialisation, alerte de sécurité —, ce qui est précisément l'exemple que
 * donne la spec. Un e-mail dont le rendu dépendait d'un objet précis ne l'est
 * pas tant que le journal n'en garde pas l'identifiant : extension possible,
 * non retenue en v1 (suivi n° 195).
 */
interface MailDataResolver
{
    /**
     * Les données de rendu re-calculées, ou `null` si elles ne le sont plus.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(MailLogEntry $entry): ?array;
}
