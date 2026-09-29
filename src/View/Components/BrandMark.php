<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Support\PublishBrandAssets;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::brand-mark />` — icône du produit Baobab (M9 point 5, Pass D,
 * suivi n° 373 décisions 4-5), réservée à `login` : jamais le branding du
 * site (favicon/logo par tenant, `<x-baobab::admin-bar>`/la sidebar), un
 * repère fixe pour l'utilisateur avant même l'authentification.
 */
final class BrandMark extends Component
{
    public string $url;

    public function __construct(PublishBrandAssets $publish)
    {
        $this->url = $publish();
    }

    public function render(): View
    {
        return view('baobab::components.brand-mark');
    }
}
