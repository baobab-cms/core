<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Supprime une redirection (spec 07 §4) — patron exact `CreateRedirect`.
 */
final class DeleteRedirect
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Redirect $redirect): void
    {
        $this->audit->record('redirect.deleted', $redirect, ['source' => $redirect->source]);

        $redirect->delete();

        Hook::action('baobab.redirect.deleted', $redirect);

        Cache::forget(ResolveRedirectTarget::CACHE_KEY);
    }
}
