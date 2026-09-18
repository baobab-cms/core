<?php

declare(strict_types=1);

namespace Baobab\Imports\Support;

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\BelongsToRelations;
use Baobab\ContentTypes\Support\ContentEntryRules;
use Baobab\Facades\Hook;
use Baobab\Media\Actions\SyncMediaUsagesFromEntry;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Models\Media;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Moteur partagé du dry-run et de l'exécution réelle (spec 12 §5.3, cadrage
 * Pass F2, suivi n° 328) — même code exact dans les deux cas, `$commit`
 * décide seulement si les écritures survivent (§12 décision 11).
 *
 * **Ce que `$commit` gate réellement** : la création d'un content type
 * manquant (`BuildContentType`, DDL non transactionnelle sous MySQL/MariaDB —
 * jamais tentée en dry-run, qui se contente de valider que le blueprint
 * embarqué se parse) et l'écriture des octets média sur disque (jamais
 * transactionnelle non plus). Les lignes de contenu, elles, passent
 * systématiquement par une transaction — `DB::rollBack()` en dry-run, ce qui
 * rend le rapport exact plutôt que simulé : c'est littéralement le code
 * d'exécution réelle, juste annulé.
 *
 * **Défaut réel trouvé en écrivant les tests, pas en instruction** :
 * `BuildContentType` ne réenregistre jamais l'autoload PSR-4 du module
 * fraîchement créé dans le process courant (`Baobab\Modules\ModuleAutoloader`,
 * jamais appelé par `InstallModule`/`ActivateModule`) — sans conséquence pour
 * l'assistant Studio, dont la prochaine page vient d'un tout nouveau process
 * PHP-FPM qui réenregistre tous les modules actifs à son propre boot, mais
 * bloquant ici : le job (ou le CLI) doit utiliser la classe du type tout
 * juste créé **dans le même process** qui vient de le construire. Corrigé en
 * appelant `ModuleAutoloader::registerFor()` immédiatement après chaque
 * `BuildContentType` réel.
 *
 * **Import en deux passes** (§12 décision 10) : la passe 1 écrit toutes les
 * lignes de tous les content types traités, relations laissées de côté ; la
 * passe 2 les résout une fois que l'identifiant portable de chaque ligne
 * touchée par cet import existe dans `$portableIdMap` — y compris pour une
 * relation vers un type qui n'appartient pas à cet archive mais existe déjà
 * localement (repli par requête directe, `resolveExistingTarget()`).
 */
final class ImportPipeline
{
    public function __construct(
        private readonly ContentEntryRules $rules,
        private readonly BuildContentType $buildContentType,
        private readonly SyncMediaUsagesFromEntry $syncMediaUsages,
        private readonly ModuleAutoloader $moduleAutoloader,
    ) {}

    public function run(ImportArchiveReader $archive, string $strategy, bool $commit): ImportReport
    {
        $formatVersion = $archive->formatVersion();
        $supported = $formatVersion === (string) config('baobab.exports.format_version');

        if (! $supported) {
            return new ImportReport($formatVersion, false, [], 0, 0, [
                "Version de format non prise en charge : {$formatVersion}.",
            ]);
        }

        [$summaries, $toCreate, $errors] = $this->planContentTypes($archive);

        if ($errors !== []) {
            return new ImportReport($formatVersion, true, $summaries, 0, 0, $errors);
        }

        if ($commit) {
            foreach ($toCreate as $key) {
                $created = ($this->buildContentType)((string) json_encode($archive->blueprint($key)));
                $this->moduleAutoloader->registerFor(Module::findOrFail($created->module_id));
            }
        }

        [$mediaMap, $mediaMatched, $mediaToImport] = $this->importMedia($archive, $commit);

        $existingKeys = array_values(array_map(
            fn (ContentTypeImportSummary $s): string => $s->key,
            array_filter($summaries, fn (ContentTypeImportSummary $s): bool => ! $s->willCreate),
        ));

        $typesToProcess = $commit ? [...$existingKeys, ...$toCreate] : $existingKeys;

        DB::beginTransaction();

        try {
            [$rowSummaries, $portableIdMap, $rawRowsByType] = $this->importRows($archive, $typesToProcess, $strategy, $mediaMap);

            $this->resolveRelations($rawRowsByType, $portableIdMap);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($commit) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        $finalSummaries = array_map(
            function (ContentTypeImportSummary $s) use ($rowSummaries): ContentTypeImportSummary {
                $counts = $rowSummaries[$s->key] ?? null;

                return $counts === null ? $s : new ContentTypeImportSummary(
                    $s->key,
                    $s->willCreate,
                    $counts['created'],
                    $counts['updated'],
                    $counts['skipped'],
                    $counts['duplicated'],
                );
            },
            $summaries,
        );

        return new ImportReport($formatVersion, true, $finalSummaries, $mediaMatched, $mediaToImport, []);
    }

    /**
     * @return array{0: list<ContentTypeImportSummary>, 1: list<string>, 2: list<string>}
     */
    private function planContentTypes(ImportArchiveReader $archive): array
    {
        $summaries = [];
        $toCreate = [];
        $errors = [];

        foreach ($archive->contentTypeKeys() as $key) {
            if (ContentType::where('key', $key)->exists()) {
                $summaries[] = new ContentTypeImportSummary($key, willCreate: false);

                continue;
            }

            try {
                ContentTypeBlueprint::fromJson((string) json_encode($archive->blueprint($key)));
            } catch (InvalidBlueprintException $e) {
                $errors[] = "Content type « {$key} » : blueprint invalide ({$e->getMessage()}).";

                continue;
            }

            $summaries[] = new ContentTypeImportSummary($key, willCreate: true);
            $toCreate[] = $key;
        }

        return [$summaries, $toCreate, $errors];
    }

    /**
     * Dédupliqués par `checksum` quelle que soit la stratégie de conflit
     * choisie pour le contenu (§12 décision 11, cadrage) — une photo
     * dupliquée n'a pas la charge éditoriale d'un enregistrement de contenu.
     * En dry-run, un média non trouvé localement reste `null` dans la carte
     * (compté, jamais résolu à un id réel puisqu'il n'est jamais créé).
     *
     * @return array{0: array<string, int|null>, 1: int, 2: int}
     */
    private function importMedia(ImportArchiveReader $archive, bool $commit): array
    {
        $map = [];
        $matched = 0;
        $toImport = 0;

        foreach ($archive->mediaMetadata() as $item) {
            $uuid = (string) $item['uuid'];
            $checksum = (string) ($item['checksum'] ?? '');

            $existing = $checksum !== '' ? Media::where('checksum', $checksum)->first() : null;

            if ($existing !== null) {
                $map[$uuid] = $existing->id;
                $matched++;

                continue;
            }

            $toImport++;

            if (! $commit) {
                $map[$uuid] = null;

                continue;
            }

            $bytes = $archive->mediaFileBytes($uuid);
            $extension = pathinfo((string) ($item['file_name'] ?? ''), PATHINFO_EXTENSION);
            $disk = (string) config('baobab.media.disk', 'public');
            $path = 'media/'.now()->format('Y/m').'/'.$uuid.($extension !== '' ? ".{$extension}" : '');

            Storage::disk($disk)->put($path, $bytes);

            $media = Media::create([
                'uuid' => $uuid,
                'disk' => $disk,
                'path' => $path,
                'file_name' => (string) ($item['file_name'] ?? $uuid),
                'mime_type' => (string) ($item['mime_type'] ?? 'application/octet-stream'),
                'source' => (string) ($item['source'] ?? 'upload'),
                'external_url' => $item['external_url'] ?? null,
                'size' => (int) Storage::disk($disk)->size($path),
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'title' => $item['title'] ?? null,
                'alt' => $item['alt'] ?? null,
                'caption' => $item['caption'] ?? null,
                'description' => $item['description'] ?? null,
                'checksum' => hash('sha256', $bytes),
                'conversions' => [],
                'meta' => [],
            ]);

            if (str_starts_with($media->mime_type, 'image/') && $media->mime_type !== 'image/svg+xml') {
                GenerateMediaConversions::dispatch($media);
            }

            $map[$uuid] = $media->id;
        }

        return [$map, $matched, $toImport];
    }

    /**
     * Passe 1 (§12 décision 10) : écrit toutes les lignes de tous les content
     * types de `$typeKeys`, relations laissées de côté. `$portableIdMap` est
     * partagé entre tous les types traités (clé `"{typeKey}:{portableId}"`)
     * pour que la passe 2 résolve une relation vers n'importe lequel d'entre
     * eux, pas seulement le type courant.
     *
     * @param  list<string>  $typeKeys
     * @param  array<string, int|null>  $mediaMap
     * @return array{0: array<string, array{created: int, updated: int, skipped: int, duplicated: int}>, 1: array<string, int>, 2: array<string, list<array{record: array<string, mixed>, id: int}>>}
     */
    private function importRows(ImportArchiveReader $archive, array $typeKeys, string $strategy, array $mediaMap): array
    {
        $summaries = [];
        $portableIdMap = [];
        $rawRowsByType = [];

        foreach ($typeKeys as $key) {
            $contentType = ContentType::where('key', $key)->firstOrFail();
            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();
            /** @var list<array<string, mixed>> $fields */
            $fields = (array) ($contentType->blueprint['fields'] ?? []);
            $identifierColumn = $contentType->is_addressable ? 'slug' : 'uuid';

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $duplicated = 0;
            $rows = [];

            foreach ($archive->contentRecords($key) as $record) {
                /** @var array<string, mixed> $record */
                $record = Hook::filter('baobab.import.record', $record, $contentType);

                $portableId = (string) $record[$identifierColumn];
                /** @var Model|null $existingRow */
                $existingRow = $modelClass::where($identifierColumn, $portableId)->first();

                $data = $this->buildRowData($contentType, $fields, $record, $mediaMap);

                if ($existingRow === null) {
                    $data[$identifierColumn] = $portableId;
                    /** @var Model $row */
                    $row = $modelClass::create($data);
                    $created++;
                } else {
                    $row = match ($strategy) {
                        'ignore' => null,
                        'duplicate' => $this->createDuplicate($modelClass, $contentType, $identifierColumn, $portableId, $data),
                        default => $this->replace($existingRow, $data),
                    };

                    match ($strategy) {
                        'ignore' => $skipped++,
                        'duplicate' => $duplicated++,
                        default => $updated++,
                    };

                    $row ??= $existingRow;
                }

                $portableIdMap["{$key}:{$portableId}"] = $row->getKey();

                if ($strategy !== 'ignore' || $existingRow === null) {
                    ($this->syncMediaUsages)($contentType, $row, $this->galleryData($fields, $record, $mediaMap));
                }

                $rows[] = ['record' => $record, 'id' => (int) $row->getKey()];
            }

            $summaries[$key] = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'duplicated' => $duplicated];
            $rawRowsByType[$key] = $rows;
        }

        return [$summaries, $portableIdMap, $rawRowsByType];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replace(Model $existingRow, array $data): Model
    {
        $existingRow->update($data);

        return $existingRow;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     */
    private function createDuplicate(string $modelClass, ContentType $contentType, string $identifierColumn, string $portableId, array $data): Model
    {
        $data[$identifierColumn] = $contentType->is_addressable
            ? $this->rules->uniqueSlug($contentType, $portableId, null)
            : (string) Str::uuid();

        return $modelClass::create($data);
    }

    /**
     * Champs scalaires + `image`/`file` (résolus via `$mediaMap`) + volet
     * éditorial structurel. Exclut délibérément les relations (passe 2) et
     * `gallery` (pas de colonne propre, `media_usages` via
     * `SyncMediaUsagesFromEntry`) et n'assigne jamais `author_id` — les
     * utilisateurs sont exclus de l'archive par construction (spec §5.2).
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $record
     * @param  array<string, int|null>  $mediaMap
     * @return array<string, mixed>
     */
    private function buildRowData(ContentType $contentType, array $fields, array $record, array $mediaMap): array
    {
        $data = [
            'status' => $record['status'] ?? 'draft',
            'published_at' => $record['published_at'] ?? null,
        ];

        if ($contentType->unpublishAtColumnExists()) {
            $data['unpublish_at'] = $record['unpublish_at'] ?? null;
        }

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $type = (string) $field['type'];

            if ($type === 'gallery') {
                continue;
            }

            $data[$key] = match ($type) {
                'image', 'file' => isset($record[$key]) ? ($mediaMap[(string) $record[$key]] ?? null) : null,
                default => $record[$key] ?? null,
            };
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $record
     * @param  array<string, int|null>  $mediaMap
     * @return array<string, mixed>
     */
    private function galleryData(array $fields, array $record, array $mediaMap): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($field['type'] !== 'gallery') {
                continue;
            }

            $key = (string) $field['key'];
            /** @var list<mixed> $archivedUuids */
            $archivedUuids = (array) ($record[$key] ?? []);

            $data[$key] = array_values(array_filter(array_map(
                fn (mixed $uuid): ?int => $mediaMap[(string) $uuid] ?? null,
                $archivedUuids,
            )));
        }

        return $data;
    }

    /**
     * Passe 2 (§12 décision 10) : résout chaque relation via
     * `$portableIdMap` (une cible touchée par cet import, quel que soit son
     * content type) puis, à défaut, via une résolution directe sur une cible
     * déjà existante localement mais étrangère à cet import
     * (`resolveExistingTarget()`). Introuvable dans les deux cas : `null`
     * silencieux, patron exact `PortableIdentifierResolver` côté export (une
     * relation vers `User` s'y résout déjà ainsi).
     *
     * @param  array<string, list<array{record: array<string, mixed>, id: int}>>  $rawRowsByType
     * @param  array<string, int>  $portableIdMap
     */
    private function resolveRelations(array $rawRowsByType, array $portableIdMap): void
    {
        foreach ($rawRowsByType as $key => $rows) {
            $contentType = ContentType::where('key', $key)->firstOrFail();
            $relations = BelongsToRelations::from((array) $contentType->blueprint);

            if ($relations === []) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            foreach ($rows as $row) {
                $updates = [];

                foreach ($relations as $relation) {
                    $targetPortableId = $row['record'][$relation['key']] ?? null;

                    if ($targetPortableId === null) {
                        continue;
                    }

                    $mapKey = "{$relation['target']}:{$targetPortableId}";
                    $updates[$relation['column']] = $portableIdMap[$mapKey]
                        ?? $this->resolveExistingTarget($relation['target'], (string) $targetPortableId);
                }

                if ($updates !== []) {
                    $modelClass::where('id', $row['id'])->update($updates);
                }
            }
        }
    }

    private function resolveExistingTarget(string $targetKey, string $portableId): ?int
    {
        $target = ContentType::where('key', $targetKey)->first();

        if ($target === null) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $target->modelClass();
        $column = $target->is_addressable ? 'slug' : 'uuid';

        return $modelClass::where($column, $portableId)->value('id');
    }
}
