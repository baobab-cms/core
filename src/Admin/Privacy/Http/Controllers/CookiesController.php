<?php

declare(strict_types=1);

namespace Baobab\Admin\Privacy\Http\Controllers;

use Baobab\Privacy\Actions\BuildCookieRegister;
use Baobab\Privacy\Cookies\CookieCategory;
use Baobab\Privacy\Cookies\CookieDeclaration;
use Illuminate\Contracts\View\View;

/**
 * Écran `admin/privacy/cookies` (spec 16 §3.2, décision 23) : les cookies
 * déclarés par le Core et les modules actifs, par catégorie, et l'état de la
 * bannière. Lecture seule sur `BuildCookieRegister` — la même liste que la
 * bannière, jamais recomposée ici. Adaptateur mince, patron
 * `RegisterController` : l'autorisation vit au middleware `can:` de la route.
 */
final class CookiesController
{
    public function index(BuildCookieRegister $build): View
    {
        $register = $build();

        $categories = array_map(static fn (CookieCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'rows' => array_map(static fn (CookieDeclaration $cookie): array => [
                'name' => $cookie->name,
                'purpose' => $cookie->purpose,
                'duration' => $cookie->duration,
                'provider' => $cookie->provider ?? '—',
                'source' => $cookie->source === 'core' ? __('baobab::admin.privacy_cookies.source_core') : $cookie->source,
            ], $register->inCategory($category)),
        ], CookieCategory::cases());

        return view('baobab::admin.privacy.cookies.index', [
            'bannerShown' => $register->requiresConsent(),
            'categories' => $categories,
            'columns' => [
                ['key' => 'name', 'label' => __('baobab::admin.privacy_cookies.name')],
                ['key' => 'purpose', 'label' => __('baobab::admin.privacy_cookies.purpose')],
                ['key' => 'duration', 'label' => __('baobab::admin.privacy_cookies.duration')],
                ['key' => 'provider', 'label' => __('baobab::admin.privacy_cookies.provider')],
                ['key' => 'source', 'label' => __('baobab::admin.privacy_cookies.source')],
            ],
        ]);
    }
}
