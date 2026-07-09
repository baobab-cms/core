<?php

declare(strict_types=1);

namespace Baobab\Modules;

/**
 * Représentation validée d'un module.json (spec 01 §2.2). Lue sans exécuter de
 * code — seule sa présence et sa conformité au schéma sont requises.
 */
final readonly class ModuleManifest
{
    /**
     * @param  array<string, mixed>  $data  Manifest décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(string $json, ?ManifestValidator $validator = null): self
    {
        ($validator ?? new ManifestValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        return new self($data);
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): ?string
    {
        return $this->data['description'] ?? null;
    }

    public function version(): string
    {
        return $this->data['version'];
    }

    public function type(): string
    {
        return $this->data['type'];
    }

    public function provider(): string
    {
        return $this->data['provider'];
    }

    public function requiresCms(): ?string
    {
        return $this->data['requires']['cms'] ?? null;
    }

    public function requiresPhp(): ?string
    {
        return $this->data['requires']['php'] ?? null;
    }

    /**
     * @return array<string, string> Nom du module → contrainte de version.
     */
    public function requiresModules(): array
    {
        return $this->data['requires']['modules'] ?? [];
    }

    /**
     * @return list<array{key: string, label: string, default_roles?: list<string>}>
     */
    public function permissions(): array
    {
        return $this->data['permissions'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminMenuItems(): array
    {
        return $this->data['menus']['admin'] ?? [];
    }

    /**
     * @return list<array{key: string, class: string}>
     */
    public function widgets(): array
    {
        return $this->data['widgets'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function hooksEmitted(): array
    {
        return $this->data['hooks']['emits'] ?? [];
    }

    /**
     * @return array<string, string> Nom du hook → classe listener.
     */
    public function hooksListened(): array
    {
        return $this->data['hooks']['listens'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
