<?php

declare(strict_types=1);

namespace Baobab\Access\Http\Middleware;

use Baobab\Users\Actions\StopImpersonating;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 04 §9.1 : (1) expiration automatique — retour silencieux à l'identité
 * réelle, pas d'erreur ; (2) actions interdites pendant une impersonation —
 * la matrice rôles/permissions (admin.access.*) et la sécurité du compte
 * (admin.account.security.*, y compris le 2FA de l'utilisateur usurpé) sont
 * bloquées ; la liste s'étendra au fur et à mesure que d'autres écrans
 * sensibles (tokens API, section Système...) seront construits.
 */
final class ImpersonationGuard
{
    /** @var list<string> */
    private const BLOCKED_ROUTE_PREFIXES = [
        'admin.access.',
        'admin.account.security.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $expiresAt = Session::get('baobab.impersonation_expires_at');

        if ($expiresAt !== null && Carbon::parse($expiresAt)->isPast()) {
            app(StopImpersonating::class)('expired');
        } elseif (Session::has('baobab.impersonator_id')) {
            $routeName = $request->route()?->getName();

            foreach (self::BLOCKED_ROUTE_PREFIXES as $prefix) {
                if ($routeName !== null && str_starts_with($routeName, $prefix)) {
                    abort(403, 'This action is not available while impersonating another user.');
                }
            }
        }

        return $next($request);
    }
}
