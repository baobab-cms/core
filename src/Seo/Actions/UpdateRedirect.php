<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Met à jour une redirection existante (spec 07 §4) — patron exact
 * `CreateRedirect`. Utilisée aussi bien par l'écran admin que par le
 * listener de renommage de slug (rename A→B puis B→A : met à jour la
 * redirection existante plutôt que d'en créer une seconde).
 */
final class UpdateRedirect
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Redirect $redirect, array $data): Redirect
    {
        $redirect->fill($data);
        $redirect->save();

        $this->audit->record('redirect.updated', $redirect, $data);

        Hook::action('baobab.redirect.updated', $redirect);

        Cache::forget(ResolveRedirectTarget::CACHE_KEY);

        return $redirect;
    }
}
