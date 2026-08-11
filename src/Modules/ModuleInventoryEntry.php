<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Illuminate\Support\Str;

/**
 * Un module tel que le cycle de vie le voit : soit une ligne `modules`, soit un
 * dossier découvert sur disque, soit les deux. Les quatre statuts sont ceux de
 * la spec-modules §3 (`discovered` étant le vocabulaire de ModuleDiscovery, pas
 * un état persisté).
 */
final readonly class ModuleInventoryEntry
{
    /**
     * @param  string  $status  discovered|installed|inactive|active
     * @param  array<string, string>  $requires  Nom du module requis → contrainte de version.
     * @param  bool  $onDisk  Faux quand la ligne existe en base mais que les fichiers ont disparu.
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $version,
        public string $type,
        public string $status,
        public string $source,
        public ?int $id,
        public array $requires,
        public bool $onDisk,
    ) {}

    public function isInstalled(): bool
    {
        return $this->id !== null;
    }

    /**
     * Le nom d'un module est toujours `vendor/slug` (schéma du manifest) : les
     * routes du cycle de vie en font deux segments d'URL plutôt qu'un
     * paramètre à barre oblique échappée.
     *
     * @return array{vendor: string, slug: string}
     */
    public function routeParams(): array
    {
        return [
            'vendor' => Str::before($this->name, '/'),
            'slug' => Str::after($this->name, '/'),
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTheme(): bool
    {
        return $this->type === 'theme';
    }
}
