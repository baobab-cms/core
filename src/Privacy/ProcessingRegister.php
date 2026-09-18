<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Le registre des traitements généré (spec 16 §2.2) : une déclaration par
 * fournisseur, la liste agrégée des destinataires externes (§6), et les
 * modules actifs qui créent des tables sans avoir rien déclaré (signal
 * d'alerte, jamais un blocage).
 */
final readonly class ProcessingRegister
{
    /**
     * @param  array<string, DataDeclaration>  $declarations  clé du fournisseur → déclaration
     * @param  list<string>  $recipients  services externes distincts, tous traitements confondus
     * @param  list<string>  $undeclaredModules  noms des modules actifs sans fournisseur
     */
    public function __construct(
        public array $declarations,
        public array $recipients,
        public array $undeclaredModules,
    ) {}
}
