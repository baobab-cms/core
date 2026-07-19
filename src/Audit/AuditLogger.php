<?php

declare(strict_types=1);

namespace Baobab\Audit;

use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

final class AuditLogger
{
    /**
     * `actor_id` retombe sur le guard `sanctum` quand `baobab` n'a personne
     * (spec 08 §4.3 : « toute écriture API journalisée avec l'identité du
     * token ») — un Bearer token n'authentifie jamais le guard `baobab`
     * directement, seul `sanctum` le résout (session ou token, M7 point 2).
     * Identité du token elle-même ajoutée à `data` sans nouvelle colonne :
     * `TransientToken` (résolution par session, `Laravel\Sanctum\Guard`)
     * n'a ni id ni nom exploitable, seul un vrai `PersonalAccessToken`
     * (Bearer) est retenu.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(string $action, ?Model $subject, array $data = []): AuditEntry
    {
        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $data['token'] = ['id' => $token->id, 'name' => $token->name];
        }

        return AuditEntry::create([
            'actor_id' => Auth::guard('baobab')->id() ?? Auth::guard('sanctum')->id(),
            'impersonator_id' => Session::get('baobab.impersonator_id'),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'data' => $data,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
