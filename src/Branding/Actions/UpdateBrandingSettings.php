<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;

/**
 * Met à jour la ligne unique de réglages de marque (spec-admin.md §11.1).
 * Validation (couleur hex, médias existants) faite par l'appelant — cette
 * Action ne fait que persister, auditer et notifier, comme le reste de la
 * couche d'actions.
 */
final class UpdateBrandingSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{logo_media_id?: int|null, favicon_media_id?: int|null, primary_color?: string|null}  $data
     */
    public function __invoke(array $data): BrandingSetting
    {
        $setting = BrandingSetting::current();
        $setting->fill($data);
        $setting->save();

        $this->audit->record('branding.updated', $setting, $data);

        Hook::action('baobab.branding.updated', $setting);

        return $setting;
    }
}
