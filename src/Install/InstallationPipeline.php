<?php

declare(strict_types=1);

namespace Baobab\Install;

use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Actions\ConfigureDatabase;
use Baobab\Install\Actions\ConfigureSite;
use Baobab\Install\Actions\CreateSuperAdmin;
use Baobab\Install\Actions\FinalizeInstallation;
use Baobab\Install\Actions\RunMigrations;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Closure;

/**
 * La séquence d'installation, une fois pour les deux interfaces (spec 15 §1).
 *
 * « Un seul pipeline, deux interfaces — le bug corrigé dans l'un est corrigé
 * dans l'autre. » La commande `baobab:install` et le wizard `/install` sont
 * des clients de cette classe ; ni l'un ni l'autre ne connaît l'ordre des
 * étapes, sans quoi il y aurait deux ordres à maintenir (suivi n° 216).
 *
 * **La reprise est ici, et elle sert enfin à quelque chose.** `recordStep()`
 * existait depuis la Pass A1 sans que rien ne l'appelle : les étapes à effet
 * de bord sont désormais notées au passage, et une installation reprise après
 * coupure — le cas courant du mutualisé, dont le temps d'exécution est
 * bridé — ne les rejoue pas.
 *
 * **L'étape 1 fait exception et se rejoue toujours** : elle ne modifie rien,
 * elle coûte quelques millisecondes, et son profil est nécessaire à la
 * finalisation. La noter comme « faite » obligerait à la persister pour la
 * relire, c'est-à-dire à recopier ailleurs ce qu'on peut simplement reprendre.
 */
final readonly class InstallationPipeline
{
    public const STEP_DATABASE = 'database';

    public const STEP_MIGRATIONS = 'migrations';

    public const STEP_ACCOUNT = 'account';

    public const STEP_SITE = 'site';

    public const STEP_FINALIZATION = 'finalization';

    /** Les étapes à effet de bord, dans l'ordre. L'étape 1 n'y figure pas : voir le docblock. */
    public const RESUMABLE_STEPS = [
        self::STEP_DATABASE,
        self::STEP_MIGRATIONS,
        self::STEP_ACCOUNT,
        self::STEP_SITE,
        self::STEP_FINALIZATION,
    ];

    public function __construct(
        private InstallationState $state,
        private CheckRequirements $checkRequirements,
        private ConfigureDatabase $configureDatabase,
        private RunMigrations $runMigrations,
        private CreateSuperAdmin $createSuperAdmin,
        private ConfigureSite $configureSite,
        private FinalizeInstallation $finalizeInstallation,
    ) {}

    /**
     * @param  array<string, string>  $writablePaths  libellé => chemin, pour l'étape 1
     * @param  Closure(string, string): void|null  $onStep  appelé à l'entrée de chaque étape (clé, libellé)
     */
    public function __invoke(
        InstallationInput $input,
        EnvFile $env,
        string $publicPath,
        array $writablePaths,
        ?Closure $onStep = null,
    ): InstallationSummary {
        $skipped = [];
        $announce = static function (string $step, string $label) use ($onStep): void {
            if ($onStep !== null) {
                $onStep($step, $label);
            }
        };

        // ── Étape 1 — toujours rejouée, sans effet de bord ────────────────
        $announce('requirements', 'Prérequis');
        $report = ($this->checkRequirements)($publicPath, $writablePaths);

        if (! $report->passes()) {
            throw InstallationStepFailed::database(
                'L\'hébergement ne remplit pas '.count($report->blockingFailures())
                .' condition(s) nécessaire(s). Corrigez-les et relancez : '
                .'rien n\'a été modifié.',
            );
        }

        // ── Étape 2 ───────────────────────────────────────────────────────
        $inspection = new DatabaseInspection([], $input->database->prefix);

        if ($this->shouldRun(self::STEP_DATABASE, $skipped)) {
            $announce(self::STEP_DATABASE, 'Base de données');
            $inspection = ($this->configureDatabase)($input->database, $env, $input->appEnv);
            $this->state->recordStep(self::STEP_DATABASE);
        }

        // ── Étape 3 ───────────────────────────────────────────────────────
        if ($this->shouldRun(self::STEP_MIGRATIONS, $skipped)) {
            $announce(self::STEP_MIGRATIONS, 'Migrations');
            ($this->runMigrations)();
            $this->state->recordStep(self::STEP_MIGRATIONS);
        }

        // ── Étape 4 ───────────────────────────────────────────────────────
        $superAdmin = null;

        if ($this->shouldRun(self::STEP_ACCOUNT, $skipped)) {
            $announce(self::STEP_ACCOUNT, 'Compte');
            $superAdmin = ($this->createSuperAdmin)($input->adminEmail, $input->adminName, $input->adminPassword);
            $this->state->recordStep(self::STEP_ACCOUNT);
        }

        // ── Étape 5 ───────────────────────────────────────────────────────
        if ($this->shouldRun(self::STEP_SITE, $skipped)) {
            $announce(self::STEP_SITE, 'Site');
            ($this->configureSite)($env, $input->siteName, $input->url, $input->timezone, $input->registrationOpen);
            $this->state->recordStep(self::STEP_SITE);
        }

        // ── Étape 6 ───────────────────────────────────────────────────────
        $profile = $report->profile;

        if ($this->shouldRun(self::STEP_FINALIZATION, $skipped)) {
            $announce(self::STEP_FINALIZATION, 'Finalisation');
            $profile = ($this->finalizeInstallation)($input->version, $profile, $this->configChecksum($input), $input->optimize);
        }

        return new InstallationSummary($profile, $report, $inspection, $superAdmin, $skipped);
    }

    /**
     * @param  list<string>  $skipped
     */
    private function shouldRun(string $step, array &$skipped): bool
    {
        if ($this->state->hasCompleted($step)) {
            $skipped[] = $step;

            return false;
        }

        return true;
    }

    /**
     * Empreinte de ce qui a été installé, journalisée dans le lock (§3).
     *
     * Elle ne porte **aucun secret** : ni mot de passe de base, ni mot de
     * passe d'administrateur. Un lock est un fichier que l'on regarde pour
     * diagnostiquer, et qui finit dans une sauvegarde.
     */
    private function configChecksum(InstallationInput $input): string
    {
        return hash('sha256', implode('|', [
            $input->database->driver,
            $input->database->database,
            $input->database->prefix,
            $input->url,
            $input->timezone,
        ]));
    }
}
