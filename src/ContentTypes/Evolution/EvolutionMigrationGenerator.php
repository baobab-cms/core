<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Evolution;

use Baobab\ContentTypes\Exceptions\UnsafeTypeChangeException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\GeneratedFileChecksums;
use Baobab\ContentTypes\Generator\MigrationTimestamp;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\ContentTypes\Models\ContentType;

/**
 * Transforme un diff de blueprint (BlueprintDiffer) en migration incrémentale
 * (spec 02 §2.2). Écrit le fichier via le même anti-écrasement par checksum
 * que le reste du générateur (M3 points 1b/2/3).
 */
final class EvolutionMigrationGenerator
{
    /**
     * Conversions de type sûres — liste blanche fermée (M3 point 4, décision
     * de scope) : tout ce qui n'y figure pas est refusé, pas de procédure
     * assistée automatisée.
     *
     * @var array<string, list<string>>
     */
    private const SAFE_CONVERSIONS = [
        'text' => ['textarea', 'richtext'],
        'textarea' => ['richtext'],
        'integer' => ['decimal'],
    ];

    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly GeneratedFileChecksums $checksums,
        private readonly FieldRegistry $fields,
    ) {}

    /**
     * @param  array{
     *     added: list<array<string, mixed>>,
     *     removed: list<array<string, mixed>>,
     *     renamed: list<array{from: string, to: array<string, mixed>}>,
     *     type_changed: list<array{key: string, from_type: string, from: array<string, mixed>, to: array<string, mixed>}>,
     * }  $diff
     * @return string|null Le chemin relatif du fichier écrit, ou null si le diff est vide.
     *
     * @throws UnsafeTypeChangeException
     */
    public function generate(ContentType $contentType, array $diff, string $moduleDir): ?string
    {
        $this->assertSafe($diff['type_changed']);

        $up = [];
        $down = [];

        foreach ($diff['added'] as $field) {
            $fieldType = $this->fields->resolve((string) $field['type']);
            $up[] = '            '.$fieldType->columnDefinition((string) $field['key'], $field['options'] ?? []);
            $down[] = "            \$table->dropColumn('{$field['key']}');";
        }

        foreach ($diff['removed'] as $field) {
            $up[] = "            \$table->dropColumn('{$field['key']}');";
            $fieldType = $this->fields->resolve((string) $field['type']);
            $down[] = '            '.$fieldType->columnDefinition((string) $field['key'], $field['options'] ?? []);
        }

        foreach ($diff['renamed'] as $rename) {
            $from = $rename['from'];
            $to = (string) $rename['to']['key'];
            $up[] = "            \$table->renameColumn('{$from}', '{$to}');";
            $down[] = "            \$table->renameColumn('{$to}', '{$from}');";
        }

        foreach ($diff['type_changed'] as $change) {
            $newFieldType = $this->fields->resolve((string) $change['to']['type']);
            $up[] = $this->asChange('            '.$newFieldType->columnDefinition((string) $change['key'], $change['to']['options'] ?? []));

            $oldFieldType = $this->fields->resolve($change['from_type']);
            $down[] = $this->asChange('            '.$oldFieldType->columnDefinition((string) $change['key'], $change['from']['options'] ?? []));
        }

        if ($up === []) {
            return null;
        }

        $contents = $this->renderer->render(StubRenderer::stubPath('evolution-migration'), [
            'table_name' => $contentType->table_name,
            'up_statements' => implode("\n", $up),
            'down_statements' => implode("\n", $down),
        ]);

        $filename = 'database/migrations/'.MigrationTimestamp::generate()."_evolve_{$contentType->table_name}_table.php";
        $this->checksums->write($moduleDir, $filename, $contents);

        return $filename;
    }

    /**
     * @param  list<array{key: string, from_type: string, from: array<string, mixed>, to: array<string, mixed>}>  $typeChanges
     *
     * @throws UnsafeTypeChangeException
     */
    private function assertSafe(array $typeChanges): void
    {
        foreach ($typeChanges as $change) {
            $allowed = self::SAFE_CONVERSIONS[$change['from_type']] ?? [];
            $toType = (string) $change['to']['type'];

            if (! in_array($toType, $allowed, true)) {
                throw UnsafeTypeChangeException::forChange($change['key'], $change['from_type'], $toType);
            }
        }
    }

    private function asChange(string $columnDefinitionLine): string
    {
        return rtrim($columnDefinitionLine, ';').'->change();';
    }
}
