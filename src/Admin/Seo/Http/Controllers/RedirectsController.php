<?php

declare(strict_types=1);

namespace Baobab\Admin\Seo\Http\Controllers;

use Baobab\Seo\Actions\CreateRedirect;
use Baobab\Seo\Actions\DeleteRedirect;
use Baobab\Seo\Actions\ExportRedirectsCsv;
use Baobab\Seo\Actions\ImportRedirectsCsv;
use Baobab\Seo\Actions\UpdateRedirect;
use Baobab\Seo\Models\Redirect;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Écran « Redirections » (spec 07 §4) — CRUD, motifs jokers, import/export
 * CSV. Accès gouverné par `baobab.system.redirects.manage`
 * (routes/admin.php). Le journal des 404 (`NotFoundLogController`) est un
 * sous-écran atteint depuis la liste plutôt qu'une entrée de sidebar
 * séparée.
 */
final class RedirectsController
{
    public function index(Request $request): View
    {
        $redirects = Redirect::query()
            ->when($request->filled('q'), fn ($query) => $query->where('source', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('baobab::admin.redirects.index', [
            'redirects' => $redirects,
            'columns' => $this->columns(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('baobab::admin.redirects.create', [
            'prefillSource' => $request->query('source'),
        ]);
    }

    public function store(Request $request, CreateRedirect $action): RedirectResponse
    {
        $validated = $this->validated($request);

        $action($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.redirects.created')]);

        return redirect()->route('admin.redirects.index');
    }

    public function edit(Redirect $redirect): View
    {
        return view('baobab::admin.redirects.edit', ['redirect' => $redirect]);
    }

    public function update(Redirect $redirect, Request $request, UpdateRedirect $action): RedirectResponse
    {
        $validated = $this->validated($request, $redirect);

        $action($redirect, $validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.redirects.updated')]);

        return redirect()->route('admin.redirects.index');
    }

    public function destroy(Redirect $redirect, DeleteRedirect $action): RedirectResponse
    {
        $action($redirect);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.redirects.deleted')]);

        return redirect()->route('admin.redirects.index');
    }

    public function export(ExportRedirectsCsv $action): Response
    {
        return response($action())
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="redirects.csv"');
    }

    public function import(Request $request, ImportRedirectsCsv $action): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $result = $action($request->file('file'));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.redirects.imported', $result),
        ]);

        return redirect()->route('admin.redirects.index');
    }

    /**
     * @return array{source: string, target: string, status_code: int, is_active: bool}
     */
    private function validated(Request $request, ?Redirect $redirect = null): array
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:2048', 'starts_with:/', 'unique:redirects,source'.($redirect !== null ? ",{$redirect->id}" : '')],
            'target' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', 'in:301,302,410'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'source' => $validated['source'],
            'target' => $validated['target'],
            'status_code' => (int) $validated['status_code'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'source', 'label' => __('baobab::admin.redirects.column_source')],
            ['key' => 'target', 'label' => __('baobab::admin.redirects.column_target')],
            ['key' => 'status_code', 'label' => __('baobab::admin.redirects.column_status_code')],
            [
                'key' => 'is_active',
                'label' => __('baobab::admin.redirects.column_active'),
                'render' => fn (Redirect $redirect) => $redirect->is_active ? __('baobab::admin.redirects.active_yes') : __('baobab::admin.redirects.active_no'),
            ],
            ['key' => 'hit_count', 'label' => __('baobab::admin.redirects.column_hits')],
            [
                'key' => 'source_kind',
                'label' => __('baobab::admin.redirects.column_kind'),
                'render' => fn (Redirect $redirect) => $redirect->source_kind === 'auto' ? __('baobab::admin.redirects.kind_auto') : __('baobab::admin.redirects.kind_manual'),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (Redirect $redirect) => view('baobab::admin.redirects.partials.row-actions', ['redirect' => $redirect])->render(),
            ],
        ];
    }
}
