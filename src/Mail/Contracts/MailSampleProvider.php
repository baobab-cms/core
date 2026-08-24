<?php

declare(strict_types=1);

namespace Baobab\Mail\Contracts;

/**
 * Les **données d'exemple** d'un template pour l'aperçu en admin (spec 13
 * §3.4), déclarées par la clé `sample` du bloc `mails` (§3.1).
 *
 * Ce contrat referme un écart assumé en Pass A2 (suivi n° 190) : la spec
 * parlait de « données d'exemple déclarées par le module » alors que le bloc
 * `mails` était fermé et n'offrait aucun endroit où les déclarer. À défaut de
 * `sample`, l'aperçu retombe sur les **libellés** des variables — lisible,
 * mais ce n'est pas un rendu réaliste, et c'était tout le problème.
 *
 * Les valeurs peuvent être notées à plat en notation pointée
 * (`'user.name' => 'Awa'`) comme en tableaux imbriqués : `PlaceholderRenderer`
 * résout `user.name` en traversée, jamais comme une clé littérale.
 */
interface MailSampleProvider
{
    /**
     * @return array<string, mixed>
     */
    public function sample(): array;
}
