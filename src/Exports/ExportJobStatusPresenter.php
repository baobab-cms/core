<?php

declare(strict_types=1);

namespace Baobab\Exports;

/**
 * Traduit le statut d'un `ExportJob` (état grossier, spec 12 §12 décision 9)
 * en éléments d'affichage — patron exact `HealthCheckStatusPresenter` : vit
 * ici plutôt que dans la vue (suivi n° 138).
 */
final class ExportJobStatusPresenter
{
    public static function badgeVariant(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'running' => 'info',
            'failed' => 'danger',
            default => 'neutral',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'completed' => __('baobab::admin.export.status_completed'),
            'running' => __('baobab::admin.export.status_running'),
            'failed' => __('baobab::admin.export.status_failed'),
            default => __('baobab::admin.export.status_pending'),
        };
    }
}
