<?php

declare(strict_types=1);

namespace Baobab\Install\Http\Controllers;

use Baobab\Install\InstallSession;
use Baobab\Install\InstallToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Porte d'entrée du wizard — spec 15 §6.1 et §6.2, Pass C1.
 *
 * Cette classe ne conduit aucune étape d'installation : elle vérifie qu'on a le
 * droit d'entrer, et ouvre la session. Les étapes arrivent en Pass C2, branchées
 * sur `InstallationPipeline` — le même orchestrateur que la CLI (n° 216), pour
 * que les deux surfaces restent deux adaptateurs d'une seule séquence.
 */
final class InstallController
{
    public function gate(InstallSession $session, InstallToken $token): View|RedirectResponse
    {
        // Le jeton naît ici, au premier regard porté sur l'installateur, et
        // pas avant : un secret écrit à l'installation du paquet traînerait
        // sur des machines qui n'installeront jamais rien.
        $token->value();

        if ($session->isAuthenticated() && $session->touch()) {
            return redirect()->route('baobab.install.index');
        }

        return view('baobab::install.gate', [
            'tokenPath' => $token->path(),
        ]);
    }

    public function unlock(Request $request, InstallSession $session, InstallToken $token): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        if (! $token->matches($validated['token'])) {
            // Message unique et volontairement avare : distinguer « jeton
            // inconnu » de « jeton expiré » apprendrait quelque chose à qui
            // tâtonne, et n'aide en rien celui qui a le fichier sous les yeux.
            return back()->withErrors([
                'token' => 'Ce code ne correspond pas à celui du serveur.',
            ]);
        }

        if (! $session->claim()) {
            return back()->withErrors([
                'token' => 'Une installation est déjà en cours depuis un autre navigateur. Réessayez dans une trentaine de minutes, ou terminez-la depuis celui-là.',
            ]);
        }

        return redirect()->route('baobab.install.index');
    }

    /**
     * Coquille du wizard. La Pass C2 y branche les étapes ; la C1 se borne à
     * prouver que le socle tient — routes conditionnelles, jeton, session,
     * verrou, layout autonome et assets servis sans l'application.
     */
    public function index(): View
    {
        return view('baobab::install.index');
    }
}
