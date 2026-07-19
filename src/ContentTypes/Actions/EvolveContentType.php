<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Evolution\BlueprintDiffer;
use Baobab\ContentTypes\Evolution\EvolutionMigrationGenerator;
use Baobab\ContentTypes\Exceptions\ContentTypeNotBuiltException;
use Baobab\ContentTypes\Exceptions\DestructiveChangeNotConfirmedException;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Generator\ContentTypeModuleGenerator;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Illuminate\Support\Facades\Artisan;

/**
 * Fait évoluer un Content Type déjà construit (spec 02 §2.2) : diff des
 * champs, migration incrémentale, régénération du modèle, versionnage,
 * audit. Ne touche ni au blueprint envelope (key, label, is_addressable) ni
 * aux relations — le périmètre de ce point est le champ.
 */
final class EvolveContentType
{
    public function __construct(
        private readonly BlueprintDiffer $differ,
        private readonly EvolutionMigrationGenerator $migrations,
        private readonly ContentTypeModuleGenerator $moduleGenerator,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(ContentType $contentType, string $newBlueprintJson, bool $confirmDestructive = false): ContentType
    {
        if ($contentType->module_id === null) {
            throw ContentTypeNotBuiltException::forKey($contentType->key);
        }

        $newBlueprint = ContentTypeBlueprint::fromJson($newBlueprintJson);

        if ($newBlueprint->key() !== $contentType->key) {
            throw InvalidBlueprintException::forField('key', 'La clé du Content Type ne peut pas changer pendant une évolution.');
        }

        /** @var list<array<string, mixed>> $oldFields */
        $oldFields = $contentType->blueprint['fields'] ?? [];
        $diff = $this->differ->diff($oldFields, $newBlueprint->fields());

        if ($diff['removed'] !== [] && ! $confirmDestructive) {
            throw DestructiveChangeNotConfirmedException::forFields(array_column($diff['removed'], 'key'));
        }

        $migrationFilename = $this->migrations->generate($contentType, $diff, $contentType->moduleDir());

        if ($migrationFilename !== null) {
            Artisan::call('migrate', [
                '--path' => $contentType->moduleDir().'/'.dirname($migrationFilename),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $contentType->update([
            'blueprint' => $newBlueprint->toArray(),
            'version' => $contentType->version + 1,
        ]);
        $contentType = $contentType->fresh() ?? $contentType;

        $this->moduleGenerator->regenerateModel($contentType);
        $this->moduleGenerator->regenerateGraphqlFragment($contentType);

        $this->audit->record('content_type.evolved', $contentType, ['diff' => $diff]);

        Hook::action('baobab.content_type.evolved', $contentType, $diff);

        return $contentType;
    }
}
