<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Rendering\Models\ReadingSetting;

/**
 * Met à jour la ligne unique de réglages de lecture (spec 03 §4). Validation
 * (mode connu, type/entrée existants et cohérents) faite par l'appelant —
 * patron exact `UpdateBrandingSettings`.
 */
final class UpdateReadingSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{mode?: string|null, page_content_type_key?: string|null, page_entry_id?: int|null, posts_content_type_key?: string|null}  $data
     */
    public function __invoke(array $data): ReadingSetting
    {
        $setting = ReadingSetting::current();
        $setting->fill($data);
        $setting->save();

        $this->audit->record('reading.updated', $setting, $data);

        Hook::action('baobab.reading.updated', $setting);

        return $setting;
    }
}
