<?php

declare(strict_types=1);

namespace Baobab\Install\Console;

use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Actions\ComposeServerChecklist;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:check` — le diagnostic (spec 15 §5).
 *
 * Il **rejoue la détection et la compare** au profil journalisé dans le lock
 * (n° 210). C'était l'arbitrage : la spec disait « rejoue » au §5 et « relu »
 * au §4.1, et les deux ensemble sont la seule lecture utile. L'état initial ne
 * casse jamais une instance ; c'est la **dérive silencieuse** qui la casse —
 * un `symlink()` désactivé par l'hébergeur, une extension retirée, un PHP
 * redescendu de version, sur un site que personne n'a touché.
 *
 * S'exécute sur une instance installée comme sur une instance qui ne l'est pas
 * encore : `CheckRequirements` ne modifie rien, c'est la condition qui rend
 * cette commande utilisable avant une mise à jour.
 */
final class CheckCommand extends Command
{
    protected $signature = 'baobab:check';

    protected $description = 'Vérifie les prérequis et signale ce qui a changé depuis l\'installation.';

    public function handle(
        CheckRequirements $checkRequirements,
        InstallationState $state,
        ComposeServerChecklist $checklist,
    ): int {
        $report = $checkRequirements(public_path(), [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public' => public_path(),
        ]);

        $this->newLine();

        foreach ($report->requirements as $requirement) {
            $this->components->twoColumnDetail(
                $requirement->label.($requirement->detail === null ? '' : ' <fg=gray>'.$requirement->detail.'</>'),
                $requirement->satisfied
                    ? '<fg=#1E9079>OK</>'
                    : ($requirement->blocking ? '<fg=red>ÉCHEC</>' : '<fg=#E9A13B>AVERTISSEMENT</>'),
            );
        }

        foreach ($report->blockingFailures() as $failure) {
            if ($failure->remedy !== null) {
                $this->newLine();
                $this->components->warn($failure->label.' — '.$failure->remedy);
            }
        }

        $installed = $state->installedProfile();

        if ($installed instanceof HostingProfile) {
            $this->reportDrift($report->profile, $installed);
            $this->reportChecklist($checklist, $installed, $report->profile);
        } else {
            $this->newLine();
            $this->components->info('Ce site n\'est pas encore installé : aucun profil de référence à comparer.');
        }

        return $report->passes() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * La checklist du §7, **rendue à chaque exécution** (arbitrage B4, n° 235).
     *
     * C'est la commande qu'on lance quand quelque chose cloche, et la
     * checklist est souvent la réponse : un e-mail qui ne part pas, un article
     * qui ne se publie pas à sa date, ce sont deux crons absents. La faire
     * dépendre d'un drapeau la rendrait introuvable au moment où elle sert.
     *
     * **Le profil est celui du lock**, seul à savoir ce qu'une requête HTTP a
     * pu constater — sauf le chemin PHP, que cette commande, elle, constate
     * (arbitrage D2, n° 238).
     *
     * **Les configurations sont réécrites** à chaque passage (arbitrage D3) :
     * un site déplacé garderait sinon des fichiers aux chemins d'avant, et
     * c'est exactement la situation où l'on vient chercher de l'aide ici.
     * L'écriture est sous garde dans l'action : un `storage/` verrouillé n'a
     * jamais empêché un diagnostic de rendre son verdict.
     */
    private function reportChecklist(ComposeServerChecklist $checklist, HostingProfile $installed, HostingProfile $today): void
    {
        $ui = new InstallerOutput($this->output, quiet: false);

        $this->newLine();

        $ui->checklist(($checklist)(
            $installed->withPhpBinary($today->phpBinary ?? $installed->phpBinary),
            base_path(),
            public_path(),
            (string) config('app.url'),
            (string) config('baobab.install.state_path', storage_path('app/baobab')),
            (bool) config('app.debug'),
            (string) config('app.env'),
        ));
    }

    private function reportDrift(HostingProfile $today, HostingProfile $installed): void
    {
        $drift = $today->driftFrom($installed);

        $this->newLine();

        if ($drift === []) {
            $this->components->info('Aucun écart avec le profil relevé à l\'installation.');

            return;
        }

        // Une capacité perdue depuis l'installation est ce que cette commande
        // sert à trouver : le site marchait, il ne marche plus, et rien dans
        // le code n'a bougé.
        $this->components->warn(count($drift).' capacité(s) ont changé depuis l\'installation.');

        foreach ($drift as $capability => $change) {
            $this->components->twoColumnDetail(
                '  '.$capability,
                '<fg=gray>'.$change['avant'].'</> → <fg=#E9A13B>'.$change['apres'].'</>',
            );
        }
    }
}
