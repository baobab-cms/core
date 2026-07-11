<?php

declare(strict_types=1);

namespace Baobab\Audit\Http\Controllers;

use Baobab\Audit\Models\AuditEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AuditLogController
{
    public function index(Request $request): View
    {
        $entries = AuditEntry::query()
            ->with(['actor', 'impersonator'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->integer('actor_id')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('baobab::admin.audit.index', ['entries' => $entries]);
    }
}
