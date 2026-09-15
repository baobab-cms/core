<?php

declare(strict_types=1);

namespace Baobab\System\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Complète l'exclusion statique de `admin/*` posée sur
 * `PreventRequestsDuringMaintenance` (`BaobabServiceProvider::boot()`) —
 * celle-ci sort tout l'admin du blocage global (nécessaire pour que la
 * connexion reste possible, le middleware global tournant avant toute
 * session/auth), celle-ci réapplique le blocage à l'intérieur de l'admin
 * pour qui n'a pas la permission de bascule (spec 12 §6.1 : « l'admin reste
 * accessible... pour les utilisateurs authentifiés disposant de
 * baobab.system.maintenance.toggle » — lu au pied de la lettre, pas
 * "tout admin authentifié").
 *
 * Placé après `auth:baobab` dans le groupe de routes admin : un utilisateur
 * peut toujours se connecter pendant la maintenance, il voit ensuite cette
 * même page plutôt que le reste de l'admin s'il n'a pas la permission.
 */
final class PreventAdminAccessDuringMaintenance
{
    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->app->maintenanceMode()->active()) {
            return $next($request);
        }

        if (Auth::guard('baobab')->user()?->can('baobab.system.maintenance.toggle')) {
            return $next($request);
        }

        $data = $this->app->maintenanceMode()->data();

        $headers = [];

        if (isset($data['retry'])) {
            $headers['Retry-After'] = $data['retry'];
        }

        return response($data['template'] ?? '', $data['status'] ?? 503, $headers);
    }
}
