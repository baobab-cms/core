<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\BelongsToRelations;
use Baobab\Exports\Models\ExportJob;
use Baobab\Exports\Support\PortableIdentifierResolver;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Exporte une sélection de content types en archive `.zip` portable (spec 12
 * §5, cadrage Pass F1, suivi n° 326) — `blueprints/{key}.json` (la colonne
 * `content_types.blueprint`, déjà auto-suffisante pour reconstruire le type),
 * `content/{key}.ndjson` (tous les champs du blueprint, pas seulement
 * `apiExposedFields()` propre à l'exposition REST — une fidélité de
 * ré-import complète exige l'ensemble), `media/` (dérivé automatiquement des
 * champs `image`/`file`/`gallery`/`richtext` réellement référencés, §12
 * décision 8) et `manifest.json` (checksums sha256 par fichier).
 *
 * Toujours synchrone dans son exécution — la Job Queue (`RunContentExportJob`)
 * n'est qu'un fin wrapper pour l'admin (§12 décision 9) ; le CLI l'invoque
 * directement, patron exact `CreateBackup`. Prend un `ExportJob` déjà créé
 * (statut `pending`, sélection déjà validée par l'appelant) plutôt que de le
 * créer lui-même : la création/le dispatch diffèrent entre l'admin et le CLI,
 * l'exécution elle-même est strictement identique.
 *
 * Les utilisateurs sont exclus par défaut (spec §5.2) : `author_id` n'est
 * jamais exporté, et une relation ciblant `User` (RelationTargetResolver)
 * résout silencieusement à `null` (`PortableIdentifierResolver::forRelationTarget()`).
 * L'URL/le contenu embarqué d'un champ `richtext` n'est jamais réécrit ici —
 * la spec le confie explicitement au filtre `baobab.import.record` de F2
 * (§5.4), pas à l'export.
 */
final class ExportContent
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PortableIdentifierResolver $identifiers,
    ) {}

    public function __invoke(ExportJob $exportJob): bool
    {
        $exportJob->update(['status' => 'running', 'started_at' => now()]);

        try {
            /** @var EloquentCollection<int, ContentType> $contentTypes */
            $contentTypes = ContentType::query()->whereIn('key', $exportJob->content_type_keys)->get();

            $tmpPath = $this->writeZip($this->buildEntries($contentTypes));

            $disk = (string) config('baobab.exports.disk');
            $relativePath = trim((string) config('baobab.exports.path'), '/')."/export-{$exportJob->uuid}.zip";

            Storage::disk($disk)->put($relativePath, (string) file_get_contents($tmpPath));
            @unlink($tmpPath);

            $exportJob->update([
                'status' => 'completed',
                'file_disk' => $disk,
                'file_path' => $relativePath,
                'file_size' => Storage::disk($disk)->size($relativePath),
                'finished_at' => now(),
            ]);

            $this->audit->record('export.completed', null, [
                'export_job_id' => $exportJob->id,
                'content_types' => $exportJob->content_type_keys,
            ]);

            Hook::action('baobab.export.completed', $exportJob);

            return true;
        } catch (Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'finished_at' => now(),
            ]);

            $this->audit->record('export.failed', null, [
                'export_job_id' => $exportJob->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  EloquentCollection<int, ContentType>  $contentTypes
     * @return array<string, string> chemin relatif dans l'archive => contenu brut
     */
    private function buildEntries(EloquentCollection $contentTypes): array
    {
        $entries = [];
        $recordCount = 0;
        /** @var list<int> $mediaIds */
        $mediaIds = [];

        foreach ($contentTypes as $contentType) {
            $entries["blueprints/{$contentType->key}.json"] = $this->encode($contentType->blueprint, pretty: true);

            [$ndjson, $count, $entryMediaIds] = $this->buildContentNdjson($contentType);

            $entries["content/{$contentType->key}.ndjson"] = $ndjson;
            $recordCount += $count;
            array_push($mediaIds, ...$entryMediaIds);
        }

        [$mediaNdjson, $mediaFiles] = $this->buildMedia(array_values(array_unique($mediaIds)));

        if ($mediaNdjson !== '') {
            $entries['media/metadata.ndjson'] = $mediaNdjson;
        }

        foreach ($mediaFiles as $relativePath => $contents) {
            $entries[$relativePath] = $contents;
        }

        $checksums = [];

        foreach ($entries as $path => $contents) {
            $checksums[$path] = hash('sha256', $contents);
        }

        $entries['manifest.json'] = $this->buildManifest($contentTypes, $recordCount, count($mediaFiles), $checksums);

        return $entries;
    }

    /**
     * @return array{0: string, 1: int, 2: list<int>}
     */
    private function buildContentNdjson(ContentType $contentType): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();
        /** @var EloquentCollection<int, Model> $entries */
        $entries = $modelClass::query()->get();

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($contentType->blueprint['fields'] ?? []);
        $relations = BelongsToRelations::from($contentType->blueprint);

        $usageFieldKeys = array_values(collect($fields)
            ->whereIn('type', ['gallery', 'richtext'])
            ->pluck('key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all());

        $usagesByEntry = $this->mediaUsagesByEntry($entries, $usageFieldKeys);

        $lines = [];
        $mediaIds = [];

        foreach ($entries as $entry) {
            $usages = $usagesByEntry[$entry->getKey()] ?? [];

            $lines[] = $this->encode($this->buildRecord($contentType, $entry, $fields, $relations, $usages));

            foreach ($fields as $field) {
                if (! in_array($field['type'], ['image', 'file'], true)) {
                    continue;
                }

                $value = $entry->getAttribute((string) $field['key']);

                if ($value !== null) {
                    $mediaIds[] = (int) $value;
                }
            }

            foreach ($usages as $fieldUsages) {
                foreach ($fieldUsages as $usage) {
                    $mediaIds[] = $usage['media_id'];
                }
            }
        }

        return [$lines === [] ? '' : implode("\n", $lines)."\n", count($lines), $mediaIds];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array{key: string, column: string, target: string, label: string, required: bool}>  $relations
     * @param  array<string, list<array{media_id: int, order: int}>>  $usages  par clé de champ
     * @return array<string, mixed>
     */
    private function buildRecord(ContentType $contentType, Model $entry, array $fields, array $relations, array $usages): array
    {
        $record = [
            'id' => $entry->getKey(),
            'status' => $entry->getAttribute('status'),
            'published_at' => $this->isoOrNull($entry->getAttribute('published_at')),
        ];

        if ($contentType->unpublishAtColumnExists()) {
            $record['unpublish_at'] = $this->isoOrNull($entry->getAttribute('unpublish_at'));
        }

        $record[$contentType->is_addressable ? 'slug' : 'uuid'] = $this->identifiers->forEntry($contentType, $entry);

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $type = (string) $field['type'];

            $record[$key] = match ($type) {
                'image', 'file' => $this->identifiers->forMedia(
                    $entry->getAttribute($key) === null ? null : (int) $entry->getAttribute($key)
                ),
                'gallery' => array_values(array_filter(array_map(
                    fn (array $usage): ?string => $this->identifiers->forMedia($usage['media_id']),
                    $usages[$key] ?? [],
                ))),
                default => $entry->getAttribute($key),
            };
        }

        foreach ($relations as $relation) {
            $relatedId = $entry->getAttribute($relation['column']);

            $record[$relation['key']] = $relatedId === null
                ? null
                : $this->identifiers->forRelationTarget($relation['target'], (int) $relatedId);
        }

        return $record;
    }

    /**
     * Usages de médias (spec 06 §5, `media_usages`) pour les champs `gallery`
     * et `richtext` — les seuls dont la référence n'est pas une colonne FK
     * directe (`image`/`file`). Une seule requête pour tout le Content Type,
     * jamais une par entrée.
     *
     * @param  EloquentCollection<int, Model>  $entries
     * @param  list<string>  $fieldKeys
     * @return array<int, array<string, list<array{media_id: int, order: int}>>> par id d'entrée puis clé de champ
     */
    private function mediaUsagesByEntry(EloquentCollection $entries, array $fieldKeys): array
    {
        if ($fieldKeys === [] || $entries->isEmpty()) {
            return [];
        }

        /** @var Model $sample */
        $sample = $entries->first();

        $usages = MediaUsage::query()
            ->where('usable_type', $sample->getMorphClass())
            ->whereIn('usable_id', $entries->modelKeys())
            ->whereIn('field_key', $fieldKeys)
            ->orderBy('order')
            ->get();

        $byEntry = [];

        foreach ($usages as $usage) {
            $byEntry[$usage->usable_id][$usage->field_key][] = [
                'media_id' => $usage->media_id,
                'order' => $usage->order,
            ];
        }

        return $byEntry;
    }

    /**
     * @param  list<int>  $mediaIds
     * @return array{0: string, 1: array<string, string>} NDJSON de métadonnées, fichiers (chemin relatif => octets)
     */
    private function buildMedia(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return ['', []];
        }

        $media = Media::query()->whereIn('id', $mediaIds)->get();

        $lines = [];
        $files = [];

        foreach ($media as $item) {
            $lines[] = $this->encode([
                'uuid' => $item->uuid,
                'file_name' => $item->file_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'width' => $item->width,
                'height' => $item->height,
                'title' => $item->title,
                'alt' => $item->alt,
                'caption' => $item->caption,
                'description' => $item->description,
                'checksum' => $item->checksum,
                'source' => $item->source,
                'external_url' => $item->external_url,
            ]);

            $extension = pathinfo($item->path, PATHINFO_EXTENSION);
            $filename = $extension !== '' ? "{$item->uuid}.{$extension}" : $item->uuid;

            $files["media/files/{$filename}"] = (string) Storage::disk($item->disk)->get($item->path);
        }

        return [implode("\n", $lines)."\n", $files];
    }

    /**
     * @param  EloquentCollection<int, ContentType>  $contentTypes
     * @param  array<string, string>  $checksums
     */
    private function buildManifest(EloquentCollection $contentTypes, int $recordCount, int $mediaCount, array $checksums): string
    {
        return $this->encode([
            'manifest_version' => config('baobab.exports.format_version'),
            'baobab_version' => config('baobab.version', 'dev'),
            'created_at' => now()->toIso8601String(),
            'content_types' => $contentTypes->pluck('key')->values()->all(),
            'counts' => [
                'content_types' => $contentTypes->count(),
                'records' => $recordCount,
                'media' => $mediaCount,
            ],
            'checksums' => $checksums,
        ], pretty: true);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeZip(array $entries): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'baobab_export_');

        if ($tmpPath === false) {
            throw new RuntimeException("Impossible de créer un fichier temporaire pour l'archive d'export.");
        }

        $zip = new ZipArchive;

        if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive d'export : {$tmpPath}.");
        }

        foreach ($entries as $relativePath => $contents) {
            $zip->addFromString($relativePath, $contents);
        }

        $zip->close();

        return $tmpPath;
    }

    private function isoOrNull(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }

    private function encode(mixed $value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);

        return (string) json_encode($value, $flags);
    }
}
