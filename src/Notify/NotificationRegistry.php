<?php

declare(strict_types=1);

namespace Baobab\Notify;

use Baobab\Modules\Models\Module;
use Baobab\Notify\Exceptions\NotificationNotFoundException;

/**
 * Résout une clé de notification (`core.*` ou `{slug}.*`) vers sa déclaration
 * (spec 11 §6). Même patron que `Baobab\Mail\TemplateRegistry` : le Core
 * n'étant pas une ligne de la table `modules`, ses propres notifications sont
 * déclarées dans `config('baobab.notifications.declarations')` ; les modules
 * actifs sont lus depuis leur `manifest['notifications']`.
 */
final class NotificationRegistry
{
    public function find(string $key): NotificationDeclaration
    {
        $declaration = str_starts_with($key, 'core.')
            ? $this->findCoreDeclaration($key)
            : $this->findModuleDeclaration($key);

        if ($declaration === null) {
            throw NotificationNotFoundException::forKey($key);
        }

        return $declaration;
    }

    /**
     * Toutes les déclarations `configurable: true` (Core + modules actifs) —
     * pour la matrice de préférences (spec 11 §7).
     *
     * @return list<NotificationDeclaration>
     */
    public function configurable(): array
    {
        $declarations = [];

        /** @var list<array<string, mixed>> $coreNotifications */
        $coreNotifications = config('baobab.notifications.declarations', []);

        foreach ($coreNotifications as $notification) {
            $declarations[] = $this->fromArray($notification);
        }

        foreach (Module::where('status', 'active')->get() as $module) {
            /** @var list<array<string, mixed>> $notifications */
            $notifications = $module->manifest['notifications'] ?? [];

            foreach ($notifications as $notification) {
                $declarations[] = $this->fromArray($notification);
            }
        }

        return array_values(array_filter($declarations, fn (NotificationDeclaration $d) => $d->configurable));
    }

    private function findCoreDeclaration(string $key): ?NotificationDeclaration
    {
        /** @var list<array<string, mixed>> $notifications */
        $notifications = config('baobab.notifications.declarations', []);

        foreach ($notifications as $notification) {
            if ($notification['key'] === $key) {
                return $this->fromArray($notification);
            }
        }

        return null;
    }

    private function findModuleDeclaration(string $key): ?NotificationDeclaration
    {
        foreach (Module::where('status', 'active')->get() as $module) {
            /** @var list<array<string, mixed>> $notifications */
            $notifications = $module->manifest['notifications'] ?? [];

            foreach ($notifications as $notification) {
                if ($notification['key'] === $key) {
                    return $this->fromArray($notification);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    private function fromArray(array $notification): NotificationDeclaration
    {
        return new NotificationDeclaration(
            key: $notification['key'],
            description: $notification['description'] ?? null,
            channels: $notification['channels'],
            mailTemplate: $notification['mail_template'] ?? null,
            configurable: $notification['configurable'] ?? true,
        );
    }
}
