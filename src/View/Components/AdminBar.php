<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Rendering\ActiveThemeResolver;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::admin-bar />` — bande fine affichée sur le site public quand
 * l'acteur connecté (guard `baobab`) a accès à l'admin, patron WordPress
 * (« vous êtes connecté »). Composant Core réutilisable par n'importe quel
 * thème (comme `<x-baobab::menu>`/`<x-baobab::vite>`) — styles auto-portés
 * en CSS inline dans la vue plutôt que des classes Tailwind : un thème tiers
 * peut ne pas avoir Tailwind, la bande doit rester correcte quoi qu'il
 * arrive (spec 03 §1 : le thème ne connaît jamais les mécanismes du Core).
 * Ne rend rien pour un visiteur, ou un compte sans accès admin.
 *
 * **Porte aussi les diagnostics destinés à qui peut agir** (spec 19 §6.5).
 * Le premier est l'absence de thème actif : le message vivait auparavant sur
 * la page publique, où un visiteur le lisait sans pouvoir rien en faire. Il
 * est ici, avec le lien qui permet d'y remédier. La bande est le bon endroit
 * parce qu'elle n'existe que pour un administrateur connecté — la condition
 * d'affichage du diagnostic est déjà celle du composant.
 */
final class AdminBar extends Component
{
    public function __construct(private readonly ActiveThemeResolver $themes) {}

    public function shouldRender(): bool
    {
        $user = auth('baobab')->user();

        return $user !== null && $user->can('baobab.admin.access');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth('baobab')->user();

        return view('baobab::components.admin-bar', [
            'userName' => $user->name,
            // Résolu ici, jamais dans la vue : « y a-t-il un thème actif ? »
            // est une question qui se teste.
            'noActiveTheme' => $this->themes->current() === null,
            'canManageThemes' => $user->can('baobab.system.themes.manage'),
        ]);
    }
}
