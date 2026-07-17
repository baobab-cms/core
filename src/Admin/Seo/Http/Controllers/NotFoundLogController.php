<?php

declare(strict_types=1);

namespace Baobab\Admin\Seo\Http\Controllers;

use Baobab\Seo\Models\NotFoundHit;
use Illuminate\Contracts\View\View;

/**
 * Journal des 404 publics (spec 07 §4) — sous-écran de « Redirections »,
 * accessible avec la même permission `baobab.system.redirects.manage`
 * (routes/admin.php). Chaque ligne propose « Créer une redirection »,
 * qui pré-remplit le formulaire de création via `?source=`.
 */
final class NotFoundLogController
{
    public function index(): View
    {
        $hits = NotFoundHit::query()
            ->orderByDesc('hits')
            ->paginate(20);

        return view('baobab::admin.redirects.not-found', [
            'hits' => $hits,
            'columns' => $this->columns(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'path', 'label' => __('baobab::admin.redirects.column_path')],
            ['key' => 'hits', 'label' => __('baobab::admin.redirects.column_hits')],
            ['key' => 'referer', 'label' => __('baobab::admin.redirects.column_referer')],
            [
                'key' => 'last_hit_at',
                'label' => __('baobab::admin.redirects.column_last_hit'),
                'render' => fn (NotFoundHit $hit) => $hit->last_hit_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (NotFoundHit $hit) => view('baobab::admin.redirects.partials.create-from-hit', ['hit' => $hit])->render(),
            ],
        ];
    }
}
