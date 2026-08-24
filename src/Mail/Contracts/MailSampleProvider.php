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
 * **Les valeurs se donnent en tableaux imbriqués, jamais à plat.**
 * `PlaceholderRenderer::resolve()` traverse le chemin segment par segment :
 * `{{ user.name }}` cherche la clé `user`, puis `name` dedans. Une clé
 * littérale `'user.name' => 'Awa'` n'est jamais trouvée, et le placeholder
 * est rendu vide avec un avertissement au journal technique.
 *
 *     return ['user' => ['name' => 'Awa']];   // ✅
 *     return ['user.name' => 'Awa'];          // ❌ rendu vide
 */
interface MailSampleProvider
{
    /**
     * @return array<string, mixed>
     */
    public function sample(): array;
}
