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

        return view('baobab::admin.audit.index', [
            'entries' => $entries,
            'columns' => $this->columns(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.audit.column_date'),
                'render' => fn (AuditEntry $entry) => $entry->created_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'actor',
                'label' => __('baobab::admin.audit.column_actor'),
                'render' => fn (AuditEntry $entry) => $entry->actor !== null ? $entry->actor->name : __('baobab::admin.audit.system_actor'),
            ],
            [
                'key' => 'impersonator',
                'label' => __('baobab::admin.audit.column_impersonator'),
                'render' => fn (AuditEntry $entry) => $entry->impersonator !== null ? $entry->impersonator->name : '—',
            ],
            [
                'key' => 'action',
                'label' => __('baobab::admin.audit.column_action'),
            ],
            [
                'key' => 'data',
                'label' => __('baobab::admin.audit.column_data'),
                'render' => fn (AuditEntry $entry) => json_encode($entry->data),
            ],
            [
                'key' => 'ip_address',
                'label' => __('baobab::admin.audit.column_ip'),
            ],
        ];
    }
}
