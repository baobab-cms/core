<?php

declare(strict_types=1);

namespace Baobab\Mail;

/**
 * Une mise à jour de module a changé le défaut d'un template que l'admin avait
 * personnalisé (spec 13 §3.2). Porte les deux versions du défaut — celle vue
 * au moment de l'enregistrement, et celle que le code livre maintenant — et
 * rien d'autre : la personnalisation de l'admin n'est jamais en jeu, elle
 * n'est pas écrasée, c'est tout l'objet du dispositif.
 *
 * Le rendu du diff appartient à l'écran (Pass A2). Ce que le moteur sait dire,
 * c'est **qu'il y a divergence**, et entre quoi et quoi.
 */
final readonly class MailTemplateDrift
{
    public function __construct(
        public string $key,
        public string $previousSubject,
        public string $previousBody,
        public string $currentSubject,
        public string $currentBody,
    ) {}

    public function subjectChanged(): bool
    {
        return $this->previousSubject !== $this->currentSubject;
    }

    public function bodyChanged(): bool
    {
        return $this->previousBody !== $this->currentBody;
    }
}
