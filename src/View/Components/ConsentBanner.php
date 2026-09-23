<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Privacy\Actions\BuildCookieRegister;
use Baobab\Privacy\Cookies\CookieCategory;
use Baobab\Privacy\Support\PublishConsentAssets;
use Baobab\Rendering\RenderedTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\Component;

/**
 * `<x-baobab::consent-banner />` (spec 16 §3.2, M9 chantier 0.b Pass F1) —
 * bannière de consentement aux cookies et script `Baobab.consent`, inclus
 * par le layout d'un thème (décision 19). Placé tôt dans le `<body>`, pour
 * qu'un visiteur au clavier la rencontre avant le contenu.
 *
 * **Ne rend rien** — ni bannière, ni script — tant qu'aucun module actif ne
 * déclare une catégorie autre que `necessary` (décision 21) : une
 * installation sans traceur reste sans JavaScript côté public (spec 19 §8.3).
 *
 * **Surcharge par le thème** (support `cookie-banner`, spec 17 §5) : un
 * thème qui le déclare fournit `partials/cookie-banner.blade.php`, rendu à
 * la place de la bannière Core avec les mêmes variables (`$categories`,
 * `$necessary`). Le support déclaré est la condition, pas la seule présence
 * du fichier — un partial homonyme sans rapport ne doit pas être aspiré.
 * Le fichier est tout de même vérifié : le validateur l'exige (§6.1), mais un
 * thème installé avant ce contrôle ne doit pas casser la page.
 *
 * Le script et le balisage ne se connaissent que par les attributs `data-*`
 * documentés en tête de `consent.js` : la surcharge reste de la présentation
 * pure.
 */
final class ConsentBanner extends Component
{
    public const string THEME_PARTIAL = 'theme::partials.cookie-banner';

    public const string CORE_VIEW = 'baobab::consent.banner';

    private bool $active;

    public string $bannerView = self::CORE_VIEW;

    public string $scriptUrl = '';

    public string $fingerprint = '';

    public string $categoryKeys = '';

    public int $lifetime;

    /** @var list<array{key: string, label: string, description: string}> */
    public array $categories = [];

    /** @var array{label: string, description: string} */
    public array $necessary;

    public function __construct(BuildCookieRegister $build, RenderedTheme $rendered, PublishConsentAssets $publish)
    {
        $register = $build();
        $this->active = $register->requiresConsent();
        $this->lifetime = (int) config('baobab.privacy.consent_lifetime_days');
        $this->necessary = [
            'label' => CookieCategory::Necessary->label(),
            'description' => CookieCategory::Necessary->description(),
        ];

        if (! $this->active) {
            return;
        }

        $consentCategories = $register->consentCategories();

        $this->categories = array_map(static fn (CookieCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
        ], $consentCategories);

        $this->categoryKeys = implode(',', array_column($this->categories, 'key'));
        $this->fingerprint = $register->fingerprint();
        $this->scriptUrl = $publish();

        /** @var list<string> $supports */
        $supports = $rendered->current()?->manifest['theme']['supports'] ?? [];

        if (in_array('cookie-banner', $supports, true) && ViewFactory::exists(self::THEME_PARTIAL)) {
            $this->bannerView = self::THEME_PARTIAL;
        }
    }

    public function shouldRender(): bool
    {
        return $this->active;
    }

    public function render(): View
    {
        return view('baobab::components.consent-banner');
    }
}
