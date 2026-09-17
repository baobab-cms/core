<?php

declare(strict_types=1);

namespace Baobab\System;

/**
 * Traduit le statut d'un contrôle de santé (`Spatie\Health\Enums\Status`,
 * lu comme une simple chaîne depuis `StoredCheckResult::$status`) en
 * éléments d'affichage — patron exact `ModuleLifecyclePresenter` : vit
 * ici plutôt que dans la vue (suivi n° 138).
 */
final class HealthCheckStatusPresenter
{
    public static function badgeVariant(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'warning' => 'warning',
            default => 'danger',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'ok' => __('baobab::admin.health.status_ok'),
            'warning' => __('baobab::admin.health.status_warning'),
            default => __('baobab::admin.health.status_failed'),
        };
    }
}
