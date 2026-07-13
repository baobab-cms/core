<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Baobab\Media\Support\RichTextMediaUsageScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resynchronise les usages de médias d'une entrée de Content Type après
 * sauvegarde (M4 point 3, spec 06 §5) — appelée sur le hook
 * `baobab.content.saved` déclenché par SaveContentEntry. Pour chaque champ
 * `richtext` ou `gallery` du blueprint, remplace entièrement les usages
 * existants pour ce `(entrée, champ)` par ce qui est réellement référencé
 * maintenant — un champ dont le contenu change ne laisse jamais d'usage
 * obsolète, et ne touche pas aux usages des autres champs de la même entrée.
 *
 * `$data` (M4 point 4b-ii) porte la sélection des champs `gallery` : ce type
 * de champ n'a aucune colonne propre, sa valeur ne peut donc pas être relue
 * depuis `$entry` comme pour `richtext`.
 */
final class SyncMediaUsagesFromEntry
{
    public function __construct(private readonly RichTextMediaUsageScanner $scanner) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(ContentType $contentType, Model $entry, array $data = []): void
    {
        $fields = collect((array) ($contentType->blueprint['fields'] ?? []));

        foreach ($fields->where('type', 'richtext') as $field) {
            $fieldKey = (string) $field['key'];
            $html = (string) ($entry->getAttribute($fieldKey) ?? '');

            $this->replaceUsages($entry, $fieldKey, collect($this->scanner->scan($html))
                ->map(fn (Media $media): array => ['media_id' => $media->id, 'order' => 0]));
        }

        foreach ($fields->where('type', 'gallery') as $field) {
            $fieldKey = (string) $field['key'];

            /** @var list<mixed> $mediaIds */
            $mediaIds = (array) ($data[$fieldKey] ?? []);

            $this->replaceUsages($entry, $fieldKey, collect($mediaIds)
                ->values()
                ->map(fn (mixed $mediaId, int $order): array => ['media_id' => (int) $mediaId, 'order' => $order]));
        }
    }

    /**
     * @param  Collection<int, array{media_id: int, order: int}>  $usages
     */
    private function replaceUsages(Model $entry, string $fieldKey, Collection $usages): void
    {
        MediaUsage::query()
            ->where('usable_type', $entry->getMorphClass())
            ->where('usable_id', $entry->getKey())
            ->where('field_key', $fieldKey)
            ->delete();

        foreach ($usages as $usage) {
            MediaUsage::create([
                'media_id' => $usage['media_id'],
                'usable_type' => $entry->getMorphClass(),
                'usable_id' => $entry->getKey(),
                'field_key' => $fieldKey,
                'order' => $usage['order'],
            ]);
        }
    }
}
