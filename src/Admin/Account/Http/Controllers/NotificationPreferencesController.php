<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Controllers;

use Baobab\Notify\Actions\UpdateNotificationPreferences;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Notify\NotificationRegistry;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Écran « Notifications » du profil utilisateur (spec 11 §7) : matrice
 * type × canal, restreinte aux déclarations `configurable: true`. Chacun ne
 * voit/modifie que les siennes (scope `own` implicite, spec §7 : « un
 * administrateur ne modifie pas les préférences d'autrui »).
 *
 * Simplification v1 (documentée au suivi) : la matrice affiche toutes les
 * notifications configurables (Core + modules actifs) à tout utilisateur,
 * sans filtrage d'éligibilité par destinataire — le manifeste ne porte aucun
 * champ pour ça.
 */
final class NotificationPreferencesController
{
    public function __construct(private readonly NotificationRegistry $registry) {}

    public function show(): View
    {
        $user = $this->actor();
        $declarations = $this->registry->configurable();

        $disabled = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('enabled', false)
            ->get()
            ->map(fn (NotificationPreference $preference) => "{$preference->key}:{$preference->channel}")
            ->all();

        return view('baobab::admin.account.notifications.index', [
            'declarations' => $declarations,
            'disabled' => $disabled,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->actor();

        // Accès direct au tableau brut plutôt que $request->boolean('prefs.x.y') :
        // les clés de notification/canal contiennent des points ("core.foo"),
        // qui seraient (mal) interprétés comme des séparateurs de niveaux par
        // la notation pointée de Arr::get().
        /** @var array<string, array<string, mixed>> $rawPrefs */
        $rawPrefs = (array) $request->input('prefs', []);

        $matrix = [];

        foreach ($this->registry->configurable() as $declaration) {
            foreach ($declaration->channels as $channel) {
                $matrix[$declaration->key][$channel] = (bool) ($rawPrefs[$declaration->key][$channel] ?? false);
            }
        }

        app(UpdateNotificationPreferences::class)($user, $matrix);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.notifications.preferences_updated')]);

        return redirect()->route('admin.account.notifications.show');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
