<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Crée une redirection (spec 07 §4) — manuelle (écran `admin/redirects`) ou
 * automatique (`BaobabServiceProvider::registerSlugRedirectListener()`,
 * `source_kind: 'auto'`). Validation faite par l'appelant (patron
 * `UpdateReadingSettings`). Invalide le cache de résolution
 * (`ResolveRedirectTarget`) à chaque écriture.
 */
final class CreateRedirect
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{source: string, target: string, status_code?: int, is_active?: bool, source_kind?: string}  $data
     */
    public function __invoke(array $data): Redirect
    {
        $redirect = Redirect::create($data);

        $this->audit->record('redirect.created', $redirect, $data);

        Hook::action('baobab.redirect.created', $redirect);

        Cache::forget(ResolveRedirectTarget::CACHE_KEY);

        return $redirect;
    }
}
