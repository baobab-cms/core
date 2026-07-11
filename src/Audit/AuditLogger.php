<?php

declare(strict_types=1);

namespace Baobab\Audit;

use Baobab\Audit\Models\AuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(string $action, ?Model $subject, array $data = []): AuditEntry
    {
        return AuditEntry::create([
            'actor_id' => Auth::guard('baobab')->id(),
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
