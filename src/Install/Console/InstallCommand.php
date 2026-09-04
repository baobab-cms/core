<?php

declare(strict_types=1);

namespace Baobab\Install\Console;

use Baobab\Install\Actions\ComposeServerChecklist;
use Baobab\Install\DatabaseCredentials;
use Baobab\Install\EnvFile;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Baobab\Install\InstallationInput;
use Baobab\Install\InstallationPipeline;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallationSummary;
use Baobab\Install\InstallPaths;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * `php artisan baobab:install` — le mode CLI (spec 15 §5).
 *
 * **Adaptateur mince.** Il pose des questions, traduit les réponses en
 * `InstallationInput` et rend compte ; l'ordre des étapes et la reprise
 * vivent dans `InstallationPipeline`, que le wizard consommera pareillement
 * (§1, suivi n° 216).
 *
 * Le mot de passe ne passe **jamais** en argument : `--admin-password-env`
 * nomme une variable d'environnement, sans quoi il fuiterait dans
 * l'historique du shell et dans la liste des processus, où n'importe quel
 * utilisateur de la machine le lirait.
 */
final class InstallCommand extends Command
{
    protected $signature = 'baobab:install
        {--db-connection= : mysql, mariadb, pgsql ou sqlite}
        {--db-host= : Hôte de la base}
        {--db-port= : Port de la base}
        {--db-database= : Nom de la base}
        {--db-username= : Identifiant}
        {--db-password-env= : Variable d\'environnement portant le mot de passe de la base}
        {--db-prefix= : Préfixe de tables, si la base est partagée}
        {--admin-name= : Nom du premier administrateur}
        {--admin-email= : E-mail du premier administrateur}
        {--admin-password-env= : Variable d\'environnement portant son mot de passe}
        {--site-name= : Nom du site}
        {--url= : URL publique du site}
        {--timezone= : Fuseau horaire (défaut UTC)}
        {--closed-registration : Ferme l\'inscription front, ouverte par défaut}
        {--telemetry : Transmettre des statistiques anonymes ; rien n\'est transmis sans ce drapeau}
        {--demo-content : Poser un contenu de démonstration (deux pages, trois articles, un menu)}';

    protected $description = 'Installe Baobab : prérequis, base, migrations, compte, site, finalisation.';

    public function handle(
        InstallationPipeline $pipeline,
        InstallationState $state,
        Filesystem $files,
        ComposeServerChecklist $checklist,
    ): int {
        $interactive = ! $this->option('no-interaction');
        $ui = new InstallerOutput($this->output, quiet: ! $interactive);

        if ($state->isInstalled()) {
            $this->components->error('Ce site est déjà installé. Utilisez baobab:check pour en vérifier l\'état.');

            return self::FAILURE;
        }

        $ui->title($this->version());

        // Chemin configurable pour la même raison que `state_path` : sans ce
        // point d entree, la commande ne serait vérifiable qu en écrivant dans
        // le `.env` du projet qui la teste.
        /** @var string $envPath */
        $envPath = config('baobab.install.env_path', base_path('.env'));
        $env = new EnvFile($files, $envPath);
        $env->createFromExample($envPath.'.example');

        try {
            $input = $this->gather($interactive);
        } catch (InstallationStepFailed $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $summary = $pipeline(
                $input,
                $env,
                public_path(),
                InstallPaths::writable(),
                // Chaque étape close la précédente : c est ce qui donne une
                // ligne par étape avec son verdict, plutôt qu une ligne qui
                // se réécrit sur elle-même et se dédouble dans un journal.
                function (string $step, string $label) use ($ui): void {
                    $ui->done();
                    $ui->step($label);
                },
            );
        } catch (InstallationStepFailed $e) {
            $this->output->newLine();
            $this->components->error($e->getMessage());

            // La cause reste pour le journal, jamais pour l'écran (n° 206).
            report($e);

            return self::FAILURE;
        }

        $ui->done();
        $ui->banner();

        $this->reportSuccess($ui, $summary, $input, $checklist);

        return self::SUCCESS;
    }

    private function gather(bool $interactive): InstallationInput
    {
        $driver = $this->option('db-connection') ?: ($interactive
            ? select('Type de base de données', ['mysql', 'mariadb', 'pgsql', 'sqlite'], 'mysql')
            : null);

        if (! is_string($driver) || $driver === '') {
            throw InstallationStepFailed::database('--db-connection est requis en mode non interactif.');
        }

        $database = $this->option('db-database') ?: ($interactive ? text('Nom de la base', required: true) : null);

        if (! is_string($database) || $database === '') {
            throw InstallationStepFailed::database('--db-database est requis en mode non interactif.');
        }

        $email = $this->option('admin-email') ?: ($interactive ? text('E-mail de l\'administrateur', required: true) : null);

        if (! is_string($email) || $email === '') {
            throw InstallationStepFailed::database('--admin-email est requis en mode non interactif.');
        }

        $siteName = $this->option('site-name') ?: ($interactive ? text('Nom du site', default: 'Mon site Baobab') : 'Mon site Baobab');
        $url = $this->option('url') ?: ($interactive ? text('URL publique', default: 'http://localhost') : 'http://localhost');

        return new InstallationInput(
            database: $this->credentials($driver, (string) $database, $interactive),
            adminEmail: (string) $email,
            siteName: (string) $siteName,
            url: (string) $url,
            adminName: $this->stringOption('admin-name'),
            adminPassword: $this->secretFromEnv('admin-password-env', $interactive, 'Mot de passe de l\'administrateur'),
            timezone: $this->stringOption('timezone') ?? 'UTC',
            registrationOpen: ! $this->option('closed-registration'),
            // **Drapeau et non invite** : le §8 point 2 veut un opt-in
            // explicite, et une invite posée au milieu d'une installation
            // console obtient surtout des « oui » distraits. Absent, rien
            // n'est consenti — c'est le défaut de l'entrée comme celui de la
            // colonne (suivi n° 229, arbitrage A4).
            telemetry: (bool) $this->option('telemetry'),
            // **Invite et non seul drapeau**, à la différence de la
            // télémétrie : le §8 point 2 écarte délibérément toute invite
            // pour un opt-in dont la qualité du consentement compte (une
            // question posée en milieu d'installation console obtient
            // surtout des « oui » distraits, suivi n° 229 arbitrage A4).
            // Le contenu de démonstration n'a pas cet enjeu — c'est un choix
            // de confort, pas un consentement — et le §8 point 1 le veut
            // « proposé à l'installation » : `confirm()` le tient à la
            // lettre (suivi n° 252).
            demoContent: $this->option('demo-content')
                ? true
                : ($interactive ? confirm('Poser un contenu de démonstration (deux pages, trois articles) ?', default: false) : false),
            appEnv: (string) config('app.env', 'production'),
            version: $this->version(),
            // Interrupteur d exploitation autant que de test : certains
            // hébergements bridés échouent sur `config:cache`, et le site
            // marche très bien sans, simplement moins vite.
            optimize: (bool) config('baobab.install.optimize', true),
        );
    }

    /**
     * Les identifiants de base, demandés en entier.
     *
     * **SQLite n'en veut aucun** : un fichier n'a ni hôte, ni port, ni compte.
     * Les demander donnerait quatre invites sans objet, et l'utilisateur qui y
     * répond quand même se retrouverait avec un `.env` porteur de valeurs que
     * rien ne lit.
     *
     * Pour les autres, tout est demandé — hôte, port, identifiant, mot de
     * passe. Ne prompter que le mot de passe, comme le faisait la première
     * version, laissait l'identifiant vide : la connexion échouait alors sur
     * un message qui parlait d'identifiants qu'on n'avait jamais demandés.
     * Trouvé par l'utilisateur à la première recette.
     */
    private function credentials(string $driver, string $database, bool $interactive): DatabaseCredentials
    {
        if ($driver === 'sqlite') {
            return new DatabaseCredentials(
                driver: $driver,
                database: $database,
                prefix: $this->stringOption('db-prefix') ?? '',
            );
        }

        $host = $this->stringOption('db-host')
            ?? ($interactive ? text('Hôte de la base', default: '127.0.0.1') : null);

        $port = $this->stringOption('db-port')
            ?? ($interactive ? text('Port', default: (string) self::defaultPort($driver)) : null);

        $username = $this->stringOption('db-username')
            ?? ($interactive ? text('Identifiant de la base', required: true) : null);

        return new DatabaseCredentials(
            driver: $driver,
            database: $database,
            host: $host,
            port: $port === null || $port === '' ? null : (int) $port,
            username: $username,
            password: $this->secretFromEnv('db-password-env', $interactive, 'Mot de passe de la base'),
            prefix: $this->stringOption('db-prefix')
                ?? ($interactive ? text('Préfixe de tables (laisser vide si la base est dédiée)', default: '') : ''),
        );
    }

    private static function defaultPort(string $driver): int
    {
        return $driver === 'pgsql' ? 5432 : 3306;
    }

    /**
     * Un secret vient d'une variable d'environnement ou d'une invite, jamais
     * d'un argument de ligne de commande (§5).
     */
    private function secretFromEnv(string $option, bool $interactive, string $label): ?string
    {
        $variable = $this->stringOption($option);

        if ($variable !== null) {
            $value = getenv($variable);

            return $value === false ? null : $value;
        }

        return $interactive ? password($label) : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function version(): string
    {
        return (string) config('baobab.version', 'dev');
    }

    /**
     * Le dernier mot revient à ce qu'il reste à faire, pas à la bannière.
     *
     * La checklist du §7 est la seule partie de cet écran qui demande une
     * action ; sur un terminal de 24 lignes, c'est elle qui doit rester
     * visible quand la bannière sort par le haut.
     */
    private function reportSuccess(InstallerOutput $ui, InstallationSummary $summary, InstallationInput $input, ComposeServerChecklist $checklist): void
    {
        if ($summary->wasResumed()) {
            $ui->line('  <fg=gray>Reprise : '.count($summary->skipped).' étape(s) déjà faites n\'ont pas été rejouées.</>');
            $ui->line('');
        }

        // Le repli sur bcrypt se dit : il est annoncé, jamais subi (n° 220).
        if ($summary->hashDriver === 'bcrypt') {
            $ui->line('  <fg=gray>Hachage des mots de passe : bcrypt. Ce PHP ne propose pas argon2id ;</>');
            $ui->line('  <fg=gray>activez-le dans les options PHP de votre hébergement pour en profiter.</>');
            $ui->line('');
        }

        $ui->line('  <options=bold>Votre site est prêt.</>');
        $ui->line('  <fg=gray>Administration :</> '.rtrim($input->url, '/').'/admin');

        if ($summary->superAdmin?->generatedPassword !== null) {
            $ui->generatedPassword($summary->superAdmin->generatedPassword);
        }

        $ui->line('');

        /*
         * Le profil est celui du `summary`, donc celui **constaté à la
         * finalisation** : `symlink()` peut exister et échouer quand même, et
         * la checklist ne doit pas proposer une manœuvre que l'hébergement
         * vient de refuser.
         *
         * Écriture des configurations sous garde côté action ; une checklist
         * qui échoue ne doit pas faire retourner un code d'erreur à une
         * installation réussie.
         */
        $ui->checklist(($checklist)(
            $summary->profile,
            base_path(),
            public_path(),
            $input->url,
            (string) config('baobab.install.state_path', storage_path('app/baobab')),
            (bool) config('app.debug'),
            $input->appEnv,
        ));
    }
}
