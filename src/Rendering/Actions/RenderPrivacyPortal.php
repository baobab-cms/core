<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\Forms\Support\FormSpamGuard;
use Baobab\Rendering\TemplateHierarchyResolver;
use Baobab\Seo\Actions\ComposeSeoMeta;
use Baobab\Seo\SeoContext;
use Illuminate\Http\Response;

/**
 * Page du portail RGPD public (spec 16 §4, spec 03 §4) — hiérarchie
 * `privacy → index` : un thème fournit `templates/privacy.blade.php`, sinon le
 * repli du Core s'applique (patron `RenderSearchPage`).
 *
 * Un seul template, plusieurs **états** (`state`) : `form`, `sent`, `confirm`
 * (`confirmUrl`, `requestType`), `confirmed` (`requestType`), `refused`
 * (`reason`), `invalid`, `used`, `delivery` (`downloadUrl`, `revealUrl`,
 * `password`, `hasPassword`, `expiresAt`), `gone`, `cancel` (`cancelUrl`,
 * `scheduledFor`), `cancelled` et `not_cancellable`, plus
 * `renderToken` (piège temporel du formulaire de saisie). Un thème qui
 * surcharge le template les traite tous. Jamais indexée ni mise en cache :
 * ces pages portent des liens à usage unique.
 */
final class RenderPrivacyPortal
{
    public function __construct(
        private readonly TemplateHierarchyResolver $hierarchy,
        private readonly ComposeSeoMeta $seo,
        private readonly SeoContext $seoContext,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(string $state, array $data = [], int $status = 200): Response
    {
        $seo = ($this->seo)(null, null);
        $seo['title'] = __('baobab::privacy.portal.title');
        $seo['robots_noindex'] = true;
        $seo['robots_nofollow'] = true;
        $this->seoContext->set($seo);

        return response()
            ->view($this->hierarchy->resolve(['privacy', 'index']), ['state' => $state, 'renderToken' => FormSpamGuard::renderToken()] + $data, $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
