<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Contracts\MailSampleProvider;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Support\MailTemplateVariables;

/**
 * Ce que le **code** déclare d'un template d'e-mail (spec 13 §3.1) : sa clé,
 * qui le fournit, son contrat de variables, le chemin de ses défauts et — depuis
 * la Pass B1 — les deux classes optionnelles qui le rendent renvoyable
 * (`resolver`, §4.2) et prévisualisable avec des données réalistes (`sample`,
 * §3.4). À ne pas confondre avec `MailTemplate`, qui est le résultat *résolu* —
 * sujet et corps prêts à rendre, personnalisation admin déjà appliquée.
 *
 * `$resolver` et `$sample` sont des `class-string` résolues via le conteneur,
 * jamais des instances — patron `SearchRegistry`/`FieldRegistry` : un manifeste
 * est un fichier JSON, il ne peut porter qu'un nom, et le conteneur donne
 * l'injection de dépendances par-dessus le marché.
 */
final readonly class MailTemplateDeclaration
{
    /**
     * @param  class-string|null  $resolver
     * @param  class-string|null  $sample
     */
    public function __construct(
        public string $key,
        public string $source,
        public string $description,
        public MailTemplateVariables $variables,
        public string $defaultsPath,
        public ?string $resolver = null,
        public ?string $sample = null,
    ) {}

    /**
     * Un renvoi est-il possible pour ce template (§4.2) ? C'est la seule
     * question que l'écran du journal pose pour décider d'activer son bouton
     * ou de le griser avec l'explication — d'où une réponse ici, et non une
     * introspection refaite par chaque appelant.
     */
    public function isResendable(): bool
    {
        return $this->resolver !== null;
    }

    /**
     * Les valeurs de substitution de l'aperçu et de l'envoi de test (§3.4) :
     * celles que le module déclare quand il en déclare, les **libellés** des
     * variables sinon.
     *
     * Le repli n'est pas un pis-aller ponctuel, c'est le comportement par
     * défaut assumé (suivi n° 190) : la plupart des templates n'ont pas besoin
     * de données réalistes pour qu'on juge d'une mise en page, et exiger un
     * `sample` de chacun rendrait la déclaration lourde pour rien.
     *
     * @return array<string, mixed>
     */
    public function sampleValues(): array
    {
        if ($this->sample === null) {
            return $this->variables->sampleValues();
        }

        $provider = app($this->sample);

        if (! $provider instanceof MailSampleProvider) {
            // Pas de repli silencieux : `MailTemplateValidator` refuse
            // l'installation d'un module dont le `sample` n'honore pas le
            // contrat, donc y arriver signifie que la déclaration a été
            // altérée après coup. Retomber sur les libellés donnerait un
            // aperçu qui a l'air de marcher et ment sur ce qui l'alimente.
            throw InvalidMailTemplateException::wrongContract(
                $this->key,
                'sample',
                $this->sample,
                MailSampleProvider::class,
            );
        }

        return $provider->sample();
    }
}
