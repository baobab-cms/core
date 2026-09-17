<?php

declare(strict_types=1);

namespace Baobab\Audit\Http\Controllers;

use Baobab\Audit\Actions\ExportAuditLogCsv;
use Baobab\Audit\AuditLogger;
use Baobab\Audit\Models\AuditEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuditLogController
{
    public function index(Request $request): View
    {
        $entries = $this->filteredQuery($request)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('baobab::admin.audit.index', [
            'entries' => $entries,
            'columns' => $this->columns(),
            'objectTypeOptions' => $this->objectTypeOptions(),
        ]);
    }

    public function export(Request $request, ExportAuditLogCsv $action, AuditLogger $audit): Response
    {
        $query = $this->filteredQuery($request)->orderByDesc('created_at');
        $count = (clone $query)->count();

        $csv = $action($query);

        $audit->record('audit.exported', null, ['count' => $count]);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="audit-log.csv"');
    }

    /**
     * @return Builder<AuditEntry>
     */
    private function filteredQuery(Request $request): Builder
    {
        return AuditEntry::query()
            ->with(['actor', 'impersonator'])
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', $request->string('action')))
            ->when(
                $request->filled('actor'),
                fn (Builder $query) => $query->whereHas('actor', function (Builder $actorQuery) use ($request): void {
                    $term = $request->string('actor');
                    $actorQuery->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                }),
            )
            ->when($request->filled('auditable_type'), fn (Builder $query) => $query->where('auditable_type', $request->string('auditable_type')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->boolean('impersonations_only'), fn (Builder $query) => $query->whereNotNull('impersonator_id'));
    }

    /**
     * Types réellement présents dans le journal — un module désinstallé ne
     * laisse pas une option orpheline dans le filtre, patron
     * `MailLogController::templateOptions()`.
     *
     * @return array<string, string>
     */
    private function objectTypeOptions(): array
    {
        $options = ['' => __('baobab::admin.audit.filter_all_types')];

        foreach (AuditEntry::query()->whereNotNull('auditable_type')->distinct()->pluck('auditable_type') as $type) {
            $options[$type] = class_basename($type);
        }

        return $options;
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
                'key' => 'auditable_type',
                'label' => __('baobab::admin.audit.column_object'),
                'render' => fn (AuditEntry $entry) => $entry->auditable_type !== null ? class_basename($entry->auditable_type) : '—',
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
