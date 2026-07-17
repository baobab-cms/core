<?php

declare(strict_types=1);

namespace Baobab\View\Components;

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
 */
final class AdminBar extends Component
{
    public function shouldRender(): bool
    {
        $user = auth('baobab')->user();

        return $user !== null && $user->can('baobab.admin.access');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth('baobab')->user();

        return view('baobab::components.admin-bar', ['userName' => $user->name]);
    }
}
