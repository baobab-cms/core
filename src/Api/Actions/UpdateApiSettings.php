<?php

declare(strict_types=1);

namespace Baobab\Api\Actions;

use Baobab\Api\Models\ApiSetting;
use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;

/**
 * Met à jour la ligne unique de réglages API (spec 08 §4.3). Validation
 * faite par l'appelant — cette Action ne fait que persister, auditer et
 * notifier, comme le reste de la couche d'actions (patron `UpdateBrandingSettings`).
 */
final class UpdateApiSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{rest_enabled?: bool, rate_limit_per_minute?: int, allowed_origins?: string|null}  $data
     */
    public function __invoke(array $data): ApiSetting
    {
        $setting = ApiSetting::current();
        $setting->fill($data);
        $setting->save();

        $this->audit->record('api.settings.updated', $setting, $data);

        Hook::action('baobab.api.settings.updated', $setting);

        return $setting;
    }
}
