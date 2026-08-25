<?php

declare(strict_types=1);

namespace Baobab\Install\Console;

use Baobab\Install\Actions\CheckRequirements;
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

    public function handle(CheckRequirements $checkRequirements, InstallationState $state): int
    {
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
        } else {
            $this->newLine();
            $this->components->info('Ce site n\'est pas encore installé : aucun profil de référence à comparer.');
        }

        return $report->passes() ? self::SUCCESS : self::FAILURE;
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
