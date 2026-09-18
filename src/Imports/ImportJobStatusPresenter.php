<?php

declare(strict_types=1);

namespace Baobab\Imports;

/**
 * Traduit le statut d'un `ImportJob` (état grossier, spec 12 §12 décision 11)
 * en éléments d'affichage — patron exact `ExportJobStatusPresenter`.
 */
final class ImportJobStatusPresenter
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
            'completed' => __('baobab::admin.import.status_completed'),
            'running' => __('baobab::admin.import.status_running'),
            'failed' => __('baobab::admin.import.status_failed'),
            default => __('baobab::admin.import.status_pending'),
        };
    }
}
