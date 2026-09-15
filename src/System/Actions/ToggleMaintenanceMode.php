<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

/**
 * Wrappe le mode maintenance natif de Laravel (spec 12 §6.1) — active/
 * désactive via `Application::maintenanceMode()` directement plutôt que
 * `Artisan::call('down'/'up')`, pour pouvoir rendre `baobab::errors.maintenance`
 * à travers le thème actif (résolu pour la requête admin en cours) plutôt que
 * via `RegisterErrorViewPaths`, un mécanisme propre au contexte CLI de
 * `DownCommand`.
 *
 * Omis délibérément par rapport à `DownCommand` : le fichier
 * `storage/framework/maintenance.php` (accélérateur lu par `public/index.php`
 * avant même l'autoload Composer) — pur gain de performance, jamais
 * fonctionnellement requis : le middleware `PreventRequestsDuringMaintenance`
 * intercepte la requête un peu plus loin dans le cycle de toute façon.
 * Reproduire fidèlement son contenu dépendrait d'un chemin interne du
 * vendor Laravel, fragile pour un gain non demandé par la spec.
 */
final class ToggleMaintenanceMode
{
    public function __construct(
        private readonly Application $app,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{secret?: ?string, withSecret?: bool, retry?: int|string|null, redirect?: ?string, status?: int}  $options
     * @return string|null Le secret de contournement, à afficher une seule fois à l'opérateur (spec 12 §6.1) — jamais recalculable après coup.
     */
    public function activate(array $options = []): ?string
    {
        $retry = $options['retry'] ?? null;
        $redirect = $options['redirect'] ?? null;
        $status = $options['status'] ?? 503;
        $secret = $this->resolveSecret($options);

        $payload = [
            'redirect' => $redirect,
            'retry' => $retry,
            'refresh' => null,
            'secret' => $secret,
            'status' => $status,
            'template' => view('baobab::errors.maintenance', ['retryAfter' => $retry])->render(),
        ];

        $this->app->maintenanceMode()->activate($payload);

        $this->audit->record('system.maintenance.enabled', null, [
            'retry' => $retry,
            'redirect' => $redirect,
            'status' => $status,
        ]);

        Hook::action('baobab.maintenance.enabled', ['retry' => $retry, 'redirect' => $redirect]);

        return $secret;
    }

    public function deactivate(): void
    {
        $this->app->maintenanceMode()->deactivate();

        $this->audit->record('system.maintenance.disabled', null);

        Hook::action('baobab.maintenance.disabled');
    }

    /**
     * @param  array{secret?: ?string, withSecret?: bool}  $options
     */
    private function resolveSecret(array $options): ?string
    {
        return match (true) {
            array_key_exists('secret', $options) && $options['secret'] !== null => (string) $options['secret'],
            (bool) ($options['withSecret'] ?? false) => Str::random(),
            default => null,
        };
    }
}
