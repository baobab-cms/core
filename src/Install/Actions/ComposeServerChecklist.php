<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\ChecklistFile;
use Baobab\Install\ChecklistItem;
use Baobab\Install\HostingProfile;
use Baobab\Install\WebServer;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * La checklist des tâches serveur (spec 15 §7).
 *
 * **Une seule composition, trois adaptateurs** — écran final, sortie CLI,
 * `baobab:check` (arbitrage A1 du n° 229). Écrire trois fois la même liste
 * aurait garanti qu'elles divergent, et c'est la seule chose qu'un utilisateur
 * de mutualisé ne peut pas rattraper : il n'a ni shell pour vérifier, ni
 * moyen de savoir laquelle des trois dit vrai.
 *
 * **Gouvernée par le profil** (§4.1, amendement du 21 août 2026) : elle ne
 * propose jamais une manœuvre que l'hébergement détecté interdit. Un profil
 * qui ne sait pas se traduit par une réserve écrite, jamais par un silence ni
 * par une affirmation — c'est le tri-état de la Pass A1 mené jusqu'à l'écran.
 *
 * Les configurations serveur sont **écrites sur disque** plutôt que proposées
 * au téléchargement (arbitrage A2, n° 229) : l'écran final est rendu par la
 * requête qui vient de refermer l'installateur, et plus aucune route ne
 * pourrait servir un fichier. `storage/app/baobab/` est hors racine web, c'est
 * déjà le répertoire d'état, et une configuration serveur n'y ajoute aucun
 * secret — seulement des chemins absolus.
 */
final class ComposeServerChecklist
{
    /** Les files du Core, dans l'ordre de priorité (spec 13, spec 11). */
    private const QUEUES = 'baobab,baobab-low,default';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  string  $stateDirectory  où écrire les configurations générées
     * @return list<ChecklistItem>
     */
    public function __invoke(
        HostingProfile $profile,
        string $basePath,
        string $publicPath,
        string $appUrl,
        string $stateDirectory,
        bool $debug,
        string $env,
    ): array {
        return array_values(array_filter([
            $this->scheduler($profile, $basePath),
            $this->worker($profile, $basePath),
            $this->webServer($profile, $publicPath, $stateDirectory),
            $this->https($appUrl),
            $this->production($appUrl, $debug, $env),
            $this->firstSteps($appUrl),
        ]));
    }

    /**
     * L'entrée cron du scheduler.
     *
     * La forme est celle qu'attendent les panneaux d'hébergement (§7, amendé
     * le 2 septembre 2026, n° 230) et chaque morceau y règle un mode de panne
     * constaté : le `cd`, parce qu'un cron de panneau ne démarre pas dans le
     * répertoire du site ; le chemin PHP absolu, parce qu'il n'est pas dans le
     * `PATH` d'un cron ; la redirection, parce que sans journal l'échec d'un
     * cron mutualisé n'existe nulle part.
     */
    private function scheduler(HostingProfile $profile, string $basePath): ChecklistItem
    {
        return ChecklistItem::task(
            'cron.scheduler',
            'Planifier les tâches de Baobab',
            'Baobab publie les contenus à leur date, vide les corbeilles et purge ses journaux '
            .'depuis une tâche planifiée. Sans elle, ces travaux ne se font jamais. Ajoutez cette '
            .'ligne dans le gestionnaire de tâches planifiées de votre hébergement, à exécuter '
            .'chaque minute.',
            command: $this->command($profile, $basePath, 'schedule:run', 'cron-schedule'),
            caveat: $this->phpCaveat($profile),
        );
    }

    /**
     * Le worker de queue, **en seconde entrée cron et non en démon**.
     *
     * `--stop-when-empty` en fait une tâche bornée : un mutualisé tue les
     * processus longs, et un `queue:work` classique n'y survit pas. C'est ce
     * qui rend le worker installable là où Supervisor n'existe pas — lequel
     * devient la variante, pour qui administre son serveur (§7, n° 230).
     */
    private function worker(HostingProfile $profile, string $basePath): ChecklistItem
    {
        $supervisor = $profile->procOpen->isPresent()
            ? ' Si vous administrez votre serveur, un worker permanent sous Supervisor reste préférable : il traite les envois sans attendre la minute suivante.'
            : '';

        return ChecklistItem::task(
            'cron.worker',
            'Faire partir les e-mails et les tâches de fond',
            'Les e-mails, les notifications et les webhooks passent par une file d\'attente : '
            .'sans ce second cron, ils sont enregistrés mais jamais envoyés. La même fréquence '
            .'que ci-dessus convient.'.$supervisor,
            command: $this->command(
                $profile,
                $basePath,
                'queue:work database --queue='.self::QUEUES.' --tries=3 --timeout=120 --sleep=5 --stop-when-empty',
                'cron-queue',
            ),
            caveat: $this->phpCaveat($profile),
        );
    }

    /**
     * La configuration du serveur web, écrite sur disque.
     *
     * **Le format suit le serveur détecté**, ce qui est la seule chose que
     * l'on sache : Apache lit des `.htaccess`, Nginx ne les lit pas. Serveur
     * inconnu, on écrit les deux et on le dit — proposer au hasard ferait
     * coller à quelqu'un une configuration que son serveur ignore en silence,
     * et il croirait son site durci sans qu'il le soit.
     */
    private function webServer(HostingProfile $profile, string $publicPath, string $stateDirectory): ChecklistItem
    {
        $written = [];

        if ($profile->webServer !== WebServer::Nginx) {
            $written[] = $this->writeConfig($stateDirectory, 'Apache', 'baobab-htaccess.conf', $this->htaccess());
        }

        if ($profile->webServer !== WebServer::Apache) {
            $written[] = $this->writeConfig($stateDirectory, 'Nginx', 'baobab-nginx.conf', $this->nginx($publicPath));
        }

        $body = match ($profile->webServer) {
            WebServer::Apache => 'Votre hébergement tourne sous Apache. Les règles ci-dessous ajoutent les '
                .'en-têtes de sécurité, refusent l\'accès aux fichiers sensibles et interdisent '
                .'l\'exécution de code dans le dossier des médias. Recopiez-les à la fin du '
                .'fichier `public/.htaccess` de votre site.',
            WebServer::Nginx => 'Votre hébergement tourne sous Nginx, qui ne lit pas les fichiers `.htaccess` : '
                .'ces règles doivent être ajoutées à la configuration du serveur, puis rechargées.',
            default => 'Le serveur web n\'a pas pu être identifié, donc les deux formes ont été écrites. '
                .'Apache lit `.htaccess` ; Nginx ne le lit pas et demande que ses règles soient '
                .'ajoutées à sa propre configuration. En cas de doute, votre hébergeur le sait.',
        };

        return ChecklistItem::task(
            'server.config',
            'Durcir la configuration du serveur web',
            $body,
            files: $written,
        );
    }

    private function https(string $appUrl): ?ChecklistItem
    {
        if (str_starts_with($appUrl, 'https://')) {
            return null;
        }

        return ChecklistItem::warning(
            'https',
            'Activer HTTPS',
            'Votre site est configuré en `http://`. Les mots de passe de vos administrateurs '
            .'circulent alors en clair. La plupart des hébergements proposent un certificat '
            .'gratuit en un clic ; une fois activé, corrigez `APP_URL` dans le fichier `.env`.',
        );
    }

    /**
     * L'avertissement `APP_DEBUG` — et il n'est pas décoratif.
     *
     * Le mode debug de Laravel affiche, sur toute erreur, la trace complète et
     * **le contenu de l'environnement** : identifiants de base, clé
     * applicative, mots de passe de messagerie. Sur une URL publique, c'est
     * une divulgation, pas une gêne.
     *
     * « URL publique » est une heuristique assumée : tout ce qui n'est ni
     * `localhost`, ni une adresse de boucle locale, ni un domaine de
     * développement conventionnel. Elle se trompe dans le sens prudent — elle
     * avertit un peu trop, jamais trop peu.
     */
    private function production(string $appUrl, bool $debug, string $env): ?ChecklistItem
    {
        if (! $debug || ! $this->looksPublic($appUrl)) {
            return null;
        }

        return ChecklistItem::warning(
            'app.debug',
            'Désactiver le mode debug',
            'Votre site est en ligne avec `APP_DEBUG=true`'.($env === 'production' ? '' : ' et `APP_ENV='.$env.'`')
            .'. La moindre erreur affichera alors aux visiteurs la trace complète du code **et le '
            .'contenu de votre fichier `.env`** — mot de passe de base de données compris. '
            .'Corrigez ces deux lignes, puis rechargez la page.',
            command: 'APP_ENV=production'."\n".'APP_DEBUG=false',
        );
    }

    private function firstSteps(string $appUrl): ChecklistItem
    {
        $admin = rtrim($appUrl, '/').'/admin';

        return ChecklistItem::task(
            'first-steps',
            'Vos premiers pas',
            'Connectez-vous à l\'administration avec l\'adresse et le mot de passe que vous venez '
            .'de choisir : '.$admin.' — puis créez votre premier type de contenu depuis '
            .'Contenus → Types de contenu.',
        );
    }

    /**
     * Une ligne de cron complète, prête à coller.
     *
     * Sans chemin PHP connu, on rend un emplacement à compléter plutôt qu'une
     * devinette : une ligne fausse produit une tâche qui échoue en silence,
     * ce qui est pire que pas de ligne du tout.
     */
    private function command(HostingProfile $profile, string $basePath, string $artisan, string $log): string
    {
        $php = $profile->phpBinary->path ?? '/chemin/vers/php';

        return 'cd '.$basePath.' && '.$php.' artisan '.$artisan
            .' >> storage/logs/'.$log.'.log 2>&1';
    }

    private function phpCaveat(HostingProfile $profile): ?string
    {
        if ($profile->phpBinary === null) {
            return 'Le chemin de PHP n\'a pas pu être déterminé : remplacez `/chemin/vers/php` par '
                .'celui que votre hébergeur indique, souvent dans la section « versions de PHP » '
                .'de son panneau.';
        }

        if ($profile->phpBinary->confirmed) {
            return null;
        }

        return 'Le chemin de PHP a été déduit et non constaté : vérifiez-le dans le panneau de '
            .'votre hébergement avant d\'enregistrer la tâche. Un chemin erroné produit une tâche '
            .'qui échoue sans rien dire.';
    }

    private function looksPublic(string $appUrl): bool
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        foreach (['.test', '.local', '.localhost', '.invalid', '.example'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Écrit une configuration, **sous garde**.
     *
     * Un `storage/` non inscriptible n'a rien d'exceptionnel sur un mutualisé,
     * et cette action est appelée par l'écran qui annonce que le site est
     * installé : une exception y transformerait une réussite en page blanche.
     * L'échec se dit donc dans l'objet — `path` reste `null` — et le contenu,
     * lui, reste affiché : c'est justement le cas où l'écran est le seul moyen
     * de récupérer les règles.
     */
    private function writeConfig(string $directory, string $label, string $name, string $contents): ChecklistFile
    {
        try {
            $this->files->ensureDirectoryExists($directory);
            $this->files->put($directory.'/'.$name, $contents);
        } catch (Throwable) {
            return new ChecklistFile($label, $contents);
        }

        return new ChecklistFile($label, $contents, $directory.'/'.$name);
    }

    /**
     * Règles Apache — en-têtes de sécurité, denylist, médias non exécutables.
     *
     * `spec 06 §3.2` est non négociable sur le dernier point : « aucun fichier
     * exécutable, PHP et assimilés refusés quelle que soit la configuration ».
     * Un média téléversé est un fichier que quelqu'un d'extérieur a choisi.
     *
     * **Ces règles vont dans un `.htaccess`, et ce contexte interdit plus de
     * choses qu'il n'y paraît.** La première version, livrée en C3b1, refusait
     * l'exécution dans le stockage par un bloc `<Directory>` assorti d'un
     * `php_flag engine off` : deux erreurs, trouvées en recette le
     * 3 septembre 2026 sur un mutualisé réel, où suivre notre propre consigne
     * mettait **tout le site en erreur 500**.
     *
     *  - `<Directory>` n'est admis qu'en configuration serveur ou en vhost.
     *    Dans un `.htaccess`, Apache répond `<Directory not allowed here` et
     *    refuse de servir quoi que ce soit.
     *  - `php_flag` n'existe qu'avec PHP en module Apache. En FPM, CGI ou
     *    LSAPI — le cas de la quasi-totalité des mutualisés d'aujourd'hui —
     *    la directive est inconnue, et une directive inconnue est fatale.
     *
     * La forme retenue est une **règle de réécriture**, admise en `.htaccess`
     * et indépendante du SAPI : elle refuse la requête avant qu'aucun
     * gestionnaire ne la voie. Elle s'ajoute *après* les règles de Laravel
     * sans les gêner : celles-ci ne détournent vers le contrôleur frontal que
     * ce qui n'est **pas** un fichier existant, or c'est précisément un
     * fichier existant que l'on veut refuser ici.
     */
    private function htaccess(): string
    {
        return <<<'APACHE'
        # Baobab — durcissement, à ajouter à la fin de public/.htaccess

        <IfModule mod_headers.c>
            Header always set X-Content-Type-Options "nosniff"
            Header always set X-Frame-Options "SAMEORIGIN"
            Header always set Referrer-Policy "strict-origin-when-cross-origin"
            Header always set X-Permitted-Cross-Domain-Policies "none"
        </IfModule>

        # Fichiers qui ne doivent jamais être servis, même déplacés par erreur
        # dans la racine web.
        <FilesMatch "^(\.env.*|composer\.(json|lock)|package(-lock)?\.json|.*\.md|.*\.sqlite)$">
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>
        </FilesMatch>

        # Le stockage des médias sert des fichiers, il n'en exécute aucun
        # (spec 06 §3.2). Une règle de réécriture, et non une section Directory
        # ni un réglage PHP réservé au module Apache : aucun des deux n'est
        # admis ici, et Apache y répond par une erreur 500 sur tout le site.
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteRule ^storage/.+\.(php|phtml|phar|phps|php[0-9]|inc|hphp)$ - [F,L]
        </IfModule>
        APACHE;
    }

    private function nginx(string $publicPath): string
    {
        return <<<NGINX
        # Baobab — durcissement, à ajouter au bloc server de votre site.
        # Racine web détectée : {$publicPath}

        add_header X-Content-Type-Options "nosniff" always;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        add_header X-Permitted-Cross-Domain-Policies "none" always;

        # Fichiers qui ne doivent jamais être servis.
        location ~* ^/(\.env|composer\.(json|lock)|package(-lock)?\.json|.*\.md|.*\.sqlite)\$ {
            deny all;
        }

        # Le stockage des médias sert des fichiers, il n'en exécute aucun
        # (spec 06 §3.2).
        location ^~ /storage/ {
            location ~ \.(php|phtml|phar)\$ {
                deny all;
            }
        }
        NGINX;
    }
}
