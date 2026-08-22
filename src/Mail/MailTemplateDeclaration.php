<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Support\MailTemplateVariables;

/**
 * Ce que le **code** déclare d'un template d'e-mail (spec 13 §3.1) : sa clé,
 * qui le fournit, son contrat de variables et le chemin de ses défauts. À ne
 * pas confondre avec `MailTemplate`, qui est le résultat *résolu* — sujet et
 * corps prêts à rendre, personnalisation admin déjà appliquée.
 *
 * La distinction sert l'écran de la Pass A2 : éditer un template suppose de
 * connaître les variables disponibles et leur libellé, ce que le template
 * résolu ne porte pas.
 */
final readonly class MailTemplateDeclaration
{
    public function __construct(
        public string $key,
        public string $source,
        public string $description,
        public MailTemplateVariables $variables,
        public string $defaultsPath,
    ) {}
}
