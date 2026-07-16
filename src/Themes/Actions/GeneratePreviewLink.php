<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\URL;

/**
 * Lien signé de prévisualisation (spec 03 §7) — valide 30 minutes, ouvre une
 * session de préview pour l'administrateur qui l'ouvre (route
 * `baobab.theme-preview.enter`, `routes/theme-preview.php`). Sans écran
 * d'admin, ce lien serait affiché par `baobab:theme:preview` en CLI ; avec
 * l'écran (ce point), `ThemesController::preview()` y redirige directement.
 */
final class GeneratePreviewLink
{
    public function __invoke(Module $theme): string
    {
        return URL::temporarySignedRoute(
            'baobab.theme-preview.enter',
            now()->addMinutes(30),
            ['module' => $theme->id],
        );
    }
}
