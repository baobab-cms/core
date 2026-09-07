<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Support;

use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des événements abonnables (spec 08 §5, renvoi spec 01 §4.2).
 * `HookRegistry` ne peut pas rendre ce service : elle ne connaît que les
 * hooks ayant déjà au moins un listener enregistré (`actions()`/`filters()`
 * = introspection de `$actionListeners`), jamais un catalogue de noms
 * documentés — confirmé en lisant `HookListCommand`. Ce catalogue statique
 * comble l'écart : `config('baobab.webhooks.hooks')` pour le Core
 * (patron `notifications.declarations`, volontairement non exhaustif —
 * cycle de vie contenu + module, les plus pertinents pour un consommateur
 * externe) + `manifest['hooks']['emits']` de chaque module actif — champ
 * déjà documenté (spec 01 §2.2) mais lu par aucun autre code à ce jour.
 *
 * `config('baobab.webhooks.manual_hooks')` rejoint le catalogue au même
 * titre (sélectionnable, validé `in:`) sans être auto-câblé par
 * `BaobabServiceProvider::registerWebhookDispatchListeners()` — un domaine
 * qui a besoin de décider lui-même, par instance, si l'événement part
 * (M8 point 6, Pass E, suivi n° 278) appelle `DispatchWebhookEvent`
 * directement plutôt que de subir un câblage tout-ou-rien par nom de hook.
 */
final class WebhookEventCatalog
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $coreHooks */
        $coreHooks = config('baobab.webhooks.hooks', []);

        /** @var list<string> $manualHooks */
        $manualHooks = config('baobab.webhooks.manual_hooks', []);

        $events = [...$coreHooks, ...$manualHooks];

        if (Schema::hasTable('modules')) {
            foreach (Module::where('status', 'active')->get() as $module) {
                /** @var list<string> $emitted */
                $emitted = $module->manifest['hooks']['emits'] ?? [];

                $events = array_merge($events, $emitted);
            }
        }

        $events = array_unique($events);
        sort($events);

        return $events;
    }
}
