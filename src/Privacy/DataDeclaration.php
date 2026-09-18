<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Ce qu'un fournisseur déclare de son traitement (spec 16 §2.1/§2.2) : la
 * matière première du registre des traitements de l'exploitant. Les
 * `externalServices` (§6) sont calculés à l'appel de `describe()` — jamais
 * figés — pour suivre la configuration courante.
 */
final readonly class DataDeclaration
{
    /**
     * @param  list<string>  $externalServices
     */
    public function __construct(
        public string $title,
        public string $nature,
        public string $purpose,
        public string $legalBasis,
        public string $retention,
        public array $externalServices = [],
    ) {}

    /**
     * @return array{title: string, nature: string, purpose: string, legal_basis: string, retention: string, external_services: list<string>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'nature' => $this->nature,
            'purpose' => $this->purpose,
            'legal_basis' => $this->legalBasis,
            'retention' => $this->retention,
            'external_services' => $this->externalServices,
        ];
    }
}
