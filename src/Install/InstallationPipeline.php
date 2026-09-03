<?php

declare(strict_types=1);

namespace Baobab\Install;

use Baobab\Install\Actions\ActivateDefaultTheme;
use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Actions\ConfigureDatabase;
use Baobab\Install\Actions\ConfigureHashing;
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

    public const STEP_HASHING = 'hashing';

    public const STEP_ACCOUNT = 'account';

    public const STEP_SITE = 'site';

    public const STEP_THEME = 'theme';

    public const STEP_FINALIZATION = 'finalization';

    /** Les étapes à effet de bord, dans l'ordre. L'étape 1 n'y figure pas : voir le docblock. */
    public const RESUMABLE_STEPS = [
        self::STEP_DATABASE,
        self::STEP_MIGRATIONS,
        self::STEP_HASHING,
        self::STEP_ACCOUNT,
        self::STEP_SITE,
        self::STEP_THEME,
        self::STEP_FINALIZATION,
    ];

    /**
     * Libellés d'affichage, partagés par la console et le navigateur — les
     * deux surfaces doivent nommer une étape de la même façon, sans quoi une
     * recette faite sur l'une ne décrit plus l'autre.
     */
    private const LABELS = [
        self::STEP_DATABASE => 'Base de données',
        self::STEP_MIGRATIONS => 'Migrations',
        self::STEP_HASHING => 'Hachage',
        self::STEP_ACCOUNT => 'Compte',
        self::STEP_SITE => 'Site',
        self::STEP_THEME => 'Thème',
        self::STEP_FINALIZATION => 'Finalisation',
    ];

    public function __construct(
        private InstallationState $state,
        private CheckRequirements $checkRequirements,
        private ConfigureDatabase $configureDatabase,
        private RunMigrations $runMigrations,
        private ConfigureHashing $configureHashing,
        private CreateSuperAdmin $createSuperAdmin,
        private ConfigureSite $configureSite,
        private ActivateDefaultTheme $activateDefaultTheme,
        private FinalizeInstallation $finalizeInstallation,
    ) {}

    /**
     * @param  array<string, string>  $writablePaths  libellé => chemin, pour l'étape 1
     * @param  array<string, mixed>  $server  `$_SERVER` de la requête, vide en console
     * @param  Closure(string, string): void|null  $onStep  appelé à l'entrée de chaque étape (clé, libellé)
     */
    public function __invoke(
        InstallationInput $input,
        EnvFile $env,
        string $publicPath,
        array $writablePaths,
        ?Closure $onStep = null,
        array $server = [],
    ): InstallationSummary {
        $report = $this->requirements($publicPath, $writablePaths, $onStep, $server);

        $skipped = [];
        $inspection = new DatabaseInspection([], $input->database->prefix);
        $hashDriver = null;
        $superAdmin = null;
        $profile = $report->profile;

        foreach (self::RESUMABLE_STEPS as $step) {
            if ($this->state->hasCompleted($step)) {
                $skipped[] = $step;

                continue;
            }

            if ($onStep !== null) {
                $onStep($step, self::LABELS[$step]);
            }

            $outcome = $this->execute($step, $input, $env, $report);

            $inspection = $outcome->inspection ?? $inspection;
            $hashDriver = $outcome->hashDriver ?? $hashDriver;
            $superAdmin = $outcome->superAdmin ?? $superAdmin;
            $profile = $outcome->profile ?? $profile;
        }

        return new InstallationSummary($profile, $report, $inspection, $superAdmin, $skipped, $hashDriver);
    }

    /**
     * Exécute **la prochaine étape due**, puis rend la main — suivi n° 224.
     *
     * C'est ce que le wizard graphique appelle, une fois par requête (spec 15
     * §6.1) : `RunMigrations` est l'étape longue, et une requête unique qui
     * porterait toute la séquence se ferait tuer par les limites d'un
     * hébergement mutualisé.
     *
     * **Le web n'apprend pas la séquence pour autant.** L'ordre reste
     * `RESUMABLE_STEPS`, la table d'exécution reste `execute()`, et la console
     * emprunte exactement les mêmes. Un adaptateur qui saurait dans quel ordre
     * installer serait un second endroit où l'installation se décide — ce que
     * le §1 refuse, et ce par quoi console et navigateur finiraient par
     * diverger en silence.
     *
     * Rend `null` quand il n'y a plus rien à faire, ce qui est la façon la plus
     * simple pour l'appelant de savoir qu'il a fini sans avoir à compter.
     *
     * @param  array<string, string>  $writablePaths  libellé => chemin, pour l'étape 1
     * @param  array<string, mixed>  $server  `$_SERVER` de la requête, vide en console
     */
    public function advance(
        InstallationInput $input,
        EnvFile $env,
        string $publicPath,
        array $writablePaths,
        array $server = [],
    ): ?StepOutcome {
        // La finalisation n'est pas notée dans l'avancement : c'est le lock
        // qui l'atteste (§3). Sans cette garde, elle se rejouerait sans fin.
        if ($this->state->isInstalled()) {
            return null;
        }

        $report = $this->requirements($publicPath, $writablePaths, null, $server);

        foreach (self::RESUMABLE_STEPS as $step) {
            if ($this->state->hasCompleted($step)) {
                continue;
            }

            return $this->execute($step, $input, $env, $report);
        }

        return null;
    }

    /**
     * Étapes restant à faire, dans l'ordre — de quoi rendre une progression
     * qui ne ment pas, puisqu'elle est lue de l'avancement réel et non d'un
     * compteur tenu par l'écran.
     *
     * @return list<string>
     */
    public function remainingSteps(): array
    {
        if ($this->state->isInstalled()) {
            return [];
        }

        return array_values(array_filter(
            self::RESUMABLE_STEPS,
            fn (string $step): bool => ! $this->state->hasCompleted($step),
        ));
    }

    public static function labelFor(string $step): string
    {
        return self::LABELS[$step] ?? $step;
    }

    /**
     * Étape 1 — toujours rejouée, sans effet de bord.
     *
     * Elle ne modifie rien, coûte quelques millisecondes, et son profil est
     * nécessaire au hachage comme à la finalisation. La rejouer à chaque
     * requête du wizard est donc à la fois sans risque et nécessaire : c'est
     * ce qui permet à `advance()` de ne rien avoir à retenir entre deux appels.
     *
     * @param  array<string, string>  $writablePaths
     * @param  array<string, mixed>  $server  `$_SERVER` de la requête, vide en console
     */
    private function requirements(string $publicPath, array $writablePaths, ?Closure $onStep, array $server = []): RequirementsReport
    {
        if ($onStep !== null) {
            $onStep('requirements', 'Prérequis');
        }

        // **`$server` était accepté par l'Action et jamais transmis** : trois
        // capacités — accès shell, racine de document, serveur web — ne se
        // lisent que dans `$_SERVER`, et le profil les rendait donc `unknown`
        // même depuis le navigateur, qui les a pourtant sous la main. Le
        // tri-état de la Pass A1 marchait ; on ne lui donnait rien à constater.
        // Trouvé en lisant la première entrée d'audit réelle (n° 233).
        $report = ($this->checkRequirements)($publicPath, $writablePaths, $server);

        if (! $report->passes()) {
            throw InstallationStepFailed::database(
                'L\'hébergement ne remplit pas '.count($report->blockingFailures())
                .' condition(s) nécessaire(s). Corrigez-les et relancez : '
                .'rien n\'a été modifié.',
            );
        }

        return $report;
    }

    /**
     * La table d'exécution — **le seul endroit** qui sache ce que fait chaque
     * étape. `__invoke()` l'appelle en boucle, `advance()` une fois.
     *
     * L'avancement est noté ici, sauf pour la finalisation : c'est le lock
     * qu'elle écrit qui l'atteste (§3), et le noter deux fois donnerait deux
     * vérités à tenir d'accord.
     */
    private function execute(string $step, InstallationInput $input, EnvFile $env, RequirementsReport $report): StepOutcome
    {
        $outcome = match ($step) {
            self::STEP_DATABASE => new StepOutcome(
                $step,
                self::LABELS[$step],
                inspection: ($this->configureDatabase)($input->database, $env, $input->appEnv),
            ),
            self::STEP_MIGRATIONS => new StepOutcome(
                $step,
                self::LABELS[$step],
                details: ($this->runMigrations)(),
            ),
            // Le hachage précède le compte, et ne va pas avec les réglages de
            // site : le compte administrateur naît à l'étape suivante, et poser
            // le driver plus tard donnerait un premier compte haché autrement
            // que le reste du site (suivi n° 220).
            self::STEP_HASHING => new StepOutcome(
                $step,
                self::LABELS[$step],
                hashDriver: ($this->configureHashing)($env, $report->profile->argon2id),
            ),
            self::STEP_ACCOUNT => new StepOutcome(
                $step,
                self::LABELS[$step],
                superAdmin: ($this->createSuperAdmin)($input->adminEmail, $input->adminName, $input->adminPassword),
            ),
            self::STEP_SITE => $this->configureSiteStep($step, $input, $env),
            // Le thème vient après les réglages de site et avant la
            // finalisation : `ActivateTheme` publie des assets et enregistre
            // des emplacements de menus, que le `optimize()` de l'étape
            // suivante doit voir. Elle ne lève jamais — voir son docblock.
            self::STEP_THEME => new StepOutcome(
                $step,
                self::LABELS[$step],
                details: ($this->activateDefaultTheme)(),
            ),
            self::STEP_FINALIZATION => new StepOutcome(
                $step,
                self::LABELS[$step],
                profile: ($this->finalizeInstallation)($input->version, $report->profile, $this->configChecksum($input), $input->optimize),
            ),
            default => throw InstallationStepFailed::database('Étape d\'installation inconnue : '.$step.'.'),
        };

        if ($step !== self::STEP_FINALIZATION) {
            $this->state->recordStep($step);
        }

        return $outcome;
    }

    /**
     * `ConfigureSite` ne rend rien : sans cette enveloppe, `match` devrait
     * porter une expression qui n'en est pas une.
     */
    private function configureSiteStep(string $step, InstallationInput $input, EnvFile $env): StepOutcome
    {
        ($this->configureSite)($env, $input->siteName, $input->url, $input->timezone, $input->registrationOpen, $input->telemetry);

        return new StepOutcome($step, self::LABELS[$step]);
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
