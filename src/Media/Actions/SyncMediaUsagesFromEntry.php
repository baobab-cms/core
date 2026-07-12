<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Models\MediaUsage;
use Baobab\Media\Support\RichTextMediaUsageScanner;
use Illuminate\Database\Eloquent\Model;

/**
 * Resynchronise les usages de médias d'une entrée de Content Type après
 * sauvegarde (M4 point 3, spec 06 §5) — appelée sur le hook
 * `baobab.content.saved` déjà déclenché par SaveContentEntry (M3), aucun
 * changement côté Content Types. Pour chaque champ `richtext` du blueprint,
 * remplace entièrement les usages existants pour ce `(entrée, champ)` par
 * ce qui est réellement référencé maintenant — un champ dont le contenu
 * change ne laisse jamais d'usage obsolète, et ne touche pas aux usages
 * des autres champs de la même entrée.
 */
final class SyncMediaUsagesFromEntry
{
    public function __construct(private readonly RichTextMediaUsageScanner $scanner) {}

    public function __invoke(ContentType $contentType, Model $entry): void
    {
        $richTextFields = collect((array) ($contentType->blueprint['fields'] ?? []))
            ->where('type', 'richtext');

        foreach ($richTextFields as $field) {
            $fieldKey = (string) $field['key'];
            $html = (string) ($entry->getAttribute($fieldKey) ?? '');

            MediaUsage::query()
                ->where('usable_type', $entry->getMorphClass())
                ->where('usable_id', $entry->getKey())
                ->where('field_key', $fieldKey)
                ->delete();

            foreach ($this->scanner->scan($html) as $media) {
                MediaUsage::create([
                    'media_id' => $media->id,
                    'usable_type' => $entry->getMorphClass(),
                    'usable_id' => $entry->getKey(),
                    'field_key' => $fieldKey,
                ]);
            }
        }
    }
}
