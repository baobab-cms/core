<?php

declare(strict_types=1);

namespace Baobab\Imports\Support;

/**
 * Rapport d'import (spec 12 §5.3, cadrage Pass F2, suivi n° 328) — produit
 * aussi bien par le dry-run (jamais persisté, affiché directement) que par
 * l'exécution réelle (persisté dans `import_jobs.report`). `errors` couvre
 * l'incompatibilité de format et les blueprints illisibles ; un rapport avec
 * des erreurs ne peut jamais être confirmé (`isValid()`).
 */
final readonly class ImportReport
{
    /**
     * @param  list<ContentTypeImportSummary>  $contentTypes
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $formatVersion,
        public bool $formatVersionSupported,
        public array $contentTypes,
        public int $mediaMatched,
        public int $mediaToImport,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->formatVersionSupported && $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format_version' => $this->formatVersion,
            'format_version_supported' => $this->formatVersionSupported,
            'content_types' => array_map(
                static fn (ContentTypeImportSummary $summary): array => $summary->toArray(),
                $this->contentTypes,
            ),
            'media_matched' => $this->mediaMatched,
            'media_to_import' => $this->mediaToImport,
            'errors' => $this->errors,
        ];
    }
}
