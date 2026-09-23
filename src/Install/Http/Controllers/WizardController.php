<?php

declare(strict_types=1);

namespace Baobab\Install\Http\Controllers;

use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Actions\ComposeServerChecklist;
use Baobab\Install\Actions\FinalizeInstallation;
use Baobab\Install\ChecklistItem;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallDraft;
use Baobab\Install\InstallPaths;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Le parcours d'installation — spec 15 §6.1, Pass C2b (suivi n° 224).
 *
 * **Collecter d'abord, exécuter ensuite.** Le §6.1 exige « le retour arrière
 * possible avant finalisation » : un écran qui déclencherait son étape à la
 * volée rendrait ce retour illusoire, puisqu'on ne revient ni sur des
 * migrations passées ni sur un compte créé. Les trois écrans de saisie
 * remplissent donc un brouillon (`InstallDraft`, en session), et rien ne
 * s'écrit avant que l'utilisateur ne lance l'installation.
 *
 * **Une étape par requête ensuite.** L'écran de progression appelle
 * `advance()` autant de fois qu'il reste d'étapes — `RunMigrations` étant la
 * seule assez longue pour qu'une requête unique se fasse tuer sur un
 * mutualisé (n° 211).
 *
 * Ce contrôleur ne connaît **pas** la séquence d'installation : il collecte,
 * puis demande au pipeline d'avancer. L'ordre des étapes vit dans
 * `InstallationPipeline`, et lui seul (§1).
 */
final class WizardController
{
    /**
     * Les écrans de **saisie**, dans l'ordre — à ne pas confondre avec les six
     * étapes d'installation du §4, qui se déroulent après eux.
     */
    private const SCREENS = [
        'requirements' => 'Prérequis',
        'database' => 'Base',
        'account' => 'Compte',
        'site' => 'Site',
    ];

    /**
     * Étape 1 du §4 — les prérequis, rejoués à chaque affichage.
     *
     * Ils ne modifient rien et coûtent quelques millisecondes : les montrer à
     * jour vaut mieux que de montrer un constat vieux de dix minutes, pendant
     * lesquelles l'utilisateur a pu corriger des droits chez son hébergeur.
     */
    public function requirements(Request $request, CheckRequirements $check): View
    {
        // `$request->server->all()` : ce que le `$_SERVER` de la requête
        // apprend au profil — accès shell, racine de document, serveur web.
        // Sans lui, la détection restait `unknown` alors qu'on est précisément
        // dans le seul contexte capable de la renseigner (n° 233).
        $report = $check(public_path(), InstallPaths::writable(), $request->server->all());

        return view('baobab::install.requirements', [
            'screens' => $this->screens('requirements'),
            'report' => $report,
            'blocking' => $report->blockingFailures(),
            'advisories' => $report->advisories(),
        ]);
    }

    public function database(InstallDraft $draft): View
    {
        return view('baobab::install.database', [
            'screens' => $this->screens('database'),
            'values' => $draft->section(InstallDraft::SECTION_DATABASE),
            'drivers' => $this->drivers(),
        ]);
    }

    public function storeDatabase(Request $request, InstallDraft $draft): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:'.implode(',', array_keys($this->drivers()))],
            'database' => ['required', 'string', 'max:64'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            // Le préfixe finit dans des noms de tables : MySQL plafonne les
            // identifiants à 64 caractères, et un préfixe long y mange la place
            // du nom lui-même (suivi n° 222).
            'prefix' => ['nullable', 'string', 'max:16', 'regex:/^[a-z0-9_]*$/'],
        ]);

        $draft->put(InstallDraft::SECTION_DATABASE, array_map(
            fn ($value): string => (string) $value,
            array_filter($validated, fn ($value): bool => $value !== null),
        ));

        return redirect()->route('baobab.install.account');
    }

    public function account(InstallDraft $draft): View|RedirectResponse
    {
        if (! $draft->has(InstallDraft::SECTION_DATABASE)) {
            return redirect()->route('baobab.install.database');
        }

        return view('baobab::install.account', [
            'screens' => $this->screens('account'),
            'values' => $draft->section(InstallDraft::SECTION_ACCOUNT),
        ]);
    }

    public function storeAccount(Request $request, InstallDraft $draft): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // La politique commune du Core (spec 04 §9, décision 7) : douze
            // caractères, et aucune règle de composition. Les règles de
            // composition produisent des mots de passe courts et prévisibles ;
            // la longueur est la seule contrainte qui augmente réellement le
            // coût d'une attaque.
            'password' => ['required', 'string', Password::defaults(), 'max:255', 'confirmed'],
        ]);

        $draft->put(InstallDraft::SECTION_ACCOUNT, [
            'name' => (string) ($validated['name'] ?? ''),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return redirect()->route('baobab.install.site');
    }

    public function site(InstallDraft $draft): View|RedirectResponse
    {
        if (! $draft->has(InstallDraft::SECTION_ACCOUNT)) {
            return redirect()->route('baobab.install.account');
        }

        $values = $draft->section(InstallDraft::SECTION_SITE);

        return view('baobab::install.site', [
            'screens' => $this->screens('site'),
            'values' => $values,
            'timezones' => timezone_identifiers_list(),
            'defaultUrl' => $values['url'] ?? url('/'),
        ]);
    }

    public function storeSite(Request $request, InstallDraft $draft): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $draft->put(InstallDraft::SECTION_SITE, [
            'name' => $validated['name'],
            'url' => rtrim($validated['url'], '/'),
            'timezone' => $validated['timezone'],
            'registration_open' => $request->boolean('registration_open'),
            // Télémétrie — spec 15 §8 point 2 : opt-in explicite, décochée par
            // défaut. La C2b la **collecte** ; l'émission, le réglage en admin
            // et la page de documentation publique qu'exige le §8.2 partent en
            // brique séparée (arbitrage du n° 223).
            'telemetry' => $request->boolean('telemetry'),
            // Contenu de démonstration — spec 15 §8 point 1 : même parti pris,
            // décoché par défaut. La D2 sait déjà le poser et le retirer ; la
            // D3 lui donne enfin sa case côté wizard (suivi n° 247).
            'demo_content' => $request->boolean('demo_content'),
        ]);

        return redirect()->route('baobab.install.run');
    }

    /**
     * L'écran de progression. Il ne lance rien lui-même : il rend l'état réel
     * et laisse le navigateur demander les étapes une à une.
     */
    public function run(InstallDraft $draft, InstallationPipeline $pipeline): View|RedirectResponse
    {
        if (! $draft->isComplete()) {
            return redirect()->route('baobab.install.'.$draft->missingSections()[0]);
        }

        $remaining = $pipeline->remainingSteps();

        return view('baobab::install.run', [
            'steps' => array_map(
                fn (string $step): array => ['key' => $step, 'label' => InstallationPipeline::labelFor($step)],
                $remaining,
            ),
        ]);
    }

    /**
     * Avance d'**une** étape, et rend ce qu'elle a fait.
     *
     * Réponse JSON parce que l'écran de progression est le seul endroit du
     * wizard où le navigateur pilote : partout ailleurs, ce sont des
     * formulaires qui rechargent la page.
     */
    public function step(
        Request $request,
        InstallDraft $draft,
        InstallationPipeline $pipeline,
        Filesystem $files,
        ComposeServerChecklist $checklist,
        InstallationState $state,
    ): JsonResponse|View|RedirectResponse {
        if (! $draft->isComplete()) {
            return response()->json(['done' => true, 'error' => 'L\'installation n\'a pas été renseignée entièrement.'], 409);
        }

        $siteUrl = rtrim((string) ($draft->section(InstallDraft::SECTION_SITE)['url'] ?? url('/')), '/');
        $adminUrl = $siteUrl.'/admin';

        $env = InstallPaths::env($files);
        $env->createFromExample(base_path('.env.example'));

        try {
            $outcome = $pipeline->advance(
                $draft->toInput(
                    $this->version(),
                    (string) config('app.env'),
                    // Même interrupteur que la console (n° 217) : un
                    // `config:cache` qui survit au geste qui l'a écrit
                    // empoisonne tout ce qui suit, à commencer par une suite
                    // de tests.
                    // **Jamais pendant la requête.** `config:cache` reconstruit
                    // toute la configuration : l'exécuter ici ferait perdre le
                    // débranchement des pilotes posé au démarrage, et la réponse
                    // se terminerait sur une configuration qui n'est plus celle
                    // qui l'a produite. Elle est différée après l'envoi.
                    optimize: false,
                ),
                $env,
                public_path(),
                InstallPaths::writable(),
                // Le profil se redétecte à chaque étape (§4.1) : lui donner le
                // `$_SERVER` de *cette* requête est ce qui le rend complet
                // côté web, et c'est lui qui finira dans le lock (n° 233).
                $request->server->all(),
            );
        } catch (InstallationStepFailed $e) {
            // On rend 200 avec un drapeau d'échec plutôt qu'un code d'erreur :
            // l'échec d'une étape est une information que l'écran doit
            // afficher, pas une panne de la requête. Le navigateur s'arrête,
            // montre le message, et l'utilisateur peut revenir sur ses pas.
            if (! $request->expectsJson()) {
                return back()->withErrors(['step' => $e->getMessage()]);
            }

            return response()->json([
                'done' => true,
                'failed' => true,
                'message' => $e->getMessage(),
            ]);
        }

        $remaining = $pipeline->remainingSteps();

        // **La fin se rend dans la requête qui finalise, et pas dans la
        // suivante.** `FinalizeInstallation` écrit le lock : dès l'instant
        // d'après, `EnsureNotInstalled` rend 404 sur tout `/install/*` (§6.3).
        // Une réponse qui dirait « continuez » enverrait donc l'utilisateur
        // sur une page qui n'existe plus, au moment le plus triomphal. Trouvé
        // en écrivant le test de bout en bout, qui recevait ce 404.
        if ($outcome === null || $remaining === []) {
            // Les secrets ne survivent pas à l'installation : le brouillon
            // porte deux mots de passe et plus personne ne les relira.
            $draft->forget();

            // L'optimisation part maintenant que la réponse est prête : elle
            // s'exécutera après l'envoi, où elle ne peut plus rien casser.
            if ((bool) config('baobab.install.optimize', true)) {
                app()->terminating(fn () => app(FinalizeInstallation::class)->optimize());
            }

            $data = [
                'adminUrl' => $adminUrl,
                'items' => $this->serverChecklist($checklist, $state, $siteUrl),
            ];

            if (! $request->expectsJson()) {
                return view('baobab::install.finished', $data);
            }

            // **Le HTML de la fin est rendu ici, pas en JavaScript** (arbitrage
            // D1, n° 238). `wizard.js` l'insère tel quel : la checklist n'est
            // écrite qu'une fois, dans le même fragment que sert la page
            // autonome du repli sans JavaScript. La rendre une seconde fois en
            // JavaScript aurait garanti que les deux divergent — et c'est ce
            // que l'action unique du §7 existe pour empêcher.
            return response()->json([
                'done' => true,
                'adminUrl' => $adminUrl,
                'html' => view('baobab::install.partials.final', $data)->render(),
                'step' => $outcome?->step,
                'label' => $outcome?->label,
                'details' => $outcome->details ?? [],
            ]);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('baobab.install.run');
        }

        return response()->json([
            'done' => false,
            'step' => $outcome->step,
            'label' => $outcome->label,
            'details' => $outcome->details,
            'remaining' => $remaining,
        ]);
    }

    /**
     * La checklist des tâches serveur, pour l'écran final (§7).
     *
     * **Le profil vient du lock**, que `FinalizeInstallation` vient d'écrire
     * quelques lignes plus haut : c'est celui qui a été *constaté à la
     * finalisation*, symlink refusé compris, et non celui détecté à l'étape 1.
     * C'est aussi ce qui rend la checklist recalculable plus tard par
     * `baobab:check`, pour qui aura fermé l'onglet (arbitrage A1, n° 229).
     *
     * **Sous garde, et jusqu'au bout.** Cet écran annonce que le site est
     * installé — il l'est, le lock est écrit. Rien de ce qui suit ne vaut une
     * page d'erreur à cet instant : une liste vide reste préférable, et
     * `baobab:check` la rendra de toute façon.
     *
     * @return list<ChecklistItem>
     */
    private function serverChecklist(ComposeServerChecklist $compose, InstallationState $state, string $siteUrl): array
    {
        try {
            $profile = $state->installedProfile();

            if (! $profile instanceof HostingProfile) {
                return [];
            }

            return $compose(
                $profile,
                base_path(),
                public_path(),
                $siteUrl,
                (string) config('baobab.install.state_path', storage_path('app/baobab')),
                (bool) config('app.debug'),
                (string) config('app.env'),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Moteurs proposés — spec 15 §8 point 3 : SQLite est **interdit en
     * production**, et l'option n'y est donc pas seulement désactivée, elle
     * est absente. Une option grisée invite à chercher comment la forcer.
     *
     * @return array<string, string>
     */
    private function drivers(): array
    {
        $drivers = [
            'mysql' => 'MySQL 8 ou plus',
            'mariadb' => 'MariaDB 10.6 ou plus',
            'pgsql' => 'PostgreSQL 14 ou plus',
        ];

        if (in_array(config('app.env'), ['local', 'staging'], true)) {
            $drivers['sqlite'] = 'SQLite (évaluation et préproduction uniquement)';
        }

        return $drivers;
    }

    private function version(): string
    {
        return (string) config('baobab.version', 'dev');
    }

    /**
     * État de chaque écran de saisie pour le fil d'ariane.
     *
     * Calculé ici et non dans la vue : une vue affiche, elle ne décide pas.
     *
     * @return list<array{key: string, label: string, state: string}>
     */
    private function screens(string $current): array
    {
        $keys = array_keys(self::SCREENS);
        $position = (int) array_search($current, $keys, true);

        $screens = [];

        foreach ($keys as $index => $key) {
            $screens[] = [
                'key' => $key,
                'label' => self::SCREENS[$key],
                'state' => match (true) {
                    $key === $current => 'current',
                    $index < $position => 'done',
                    default => 'todo',
                },
            ];
        }

        return $screens;
    }
}
