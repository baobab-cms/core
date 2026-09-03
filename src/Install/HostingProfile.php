<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Profil de capacités de l'hébergement (spec 15 §4.1).
 *
 * « Détecter, jamais niveler » : les étapes suivantes s'adaptent à ce profil,
 * un dédié conservant intégralement son avantage tandis qu'un mutualisé
 * obtient un site qui fonctionne, avec ses limites énoncées plutôt que subies.
 *
 * Le profil est journalisé dans le lock (§3) et relu par `baobab:check`, qui
 * rejoue la détection et la compare — c'est la dérive silencieuse, non l'état
 * initial, qui casse une instance que personne n'a touchée (suivi n° 210).
 */
final readonly class HostingProfile
{
    public function __construct(
        public Capability $symlink,
        public Capability $procOpen,
        public Capability $shellAccess,
        public Capability $publicIsDocumentRoot,
        public WebServer $webServer,
        public Capability $argon2id = Capability::Unknown,
        /**
         * Chemin de l'interpréteur en ligne de commande, et sa certitude.
         *
         * Il n'est pas une *capacité* — on ne le lit ni présent ni absent,
         * mais quelque part — et pourtant il vit ici : c'est ce que la
         * checklist du §7 doit rendre, et c'est aussi une **dérive utile**.
         * Un hébergeur qui change de version de PHP déplace ce binaire, et la
         * ligne de cron posée à l'installation cesse alors de fonctionner sans
         * que rien ne le dise. `baobab:check` le verra (n° 235).
         */
        public ?PhpBinary $phpBinary = null,
    ) {}

    /**
     * Le même profil, symlink constaté absent.
     *
     * **Existe parce qu'un profil se recopie mal à la main** : la finalisation
     * reconstruisait un `HostingProfile` champ par champ quand `storage:link`
     * échouait, et **oubliait `argon2id`** — ajouté après elle. Le lock d'un
     * hébergement sans symlink enregistrait donc « hachage inconnu » alors
     * qu'on venait de le constater. Un constructeur nommé ne peut pas oublier
     * ce qu'il ne nomme pas.
     */
    public function withoutSymlink(): self
    {
        return new self(
            symlink: Capability::Absent,
            procOpen: $this->procOpen,
            shellAccess: $this->shellAccess,
            publicIsDocumentRoot: $this->publicIsDocumentRoot,
            webServer: $this->webServer,
            argon2id: $this->argon2id,
            phpBinary: $this->phpBinary,
        );
    }

    /**
     * Le même profil, avec le chemin PHP d'ailleurs.
     *
     * **Existe pour `baobab:check`** (arbitrage D2, n° 238). La checklist du
     * §7 est gouvernée par le profil du **lock** : le serveur web, `proc_open`
     * et le symlink ne se constatent qu'au moment de l'installation, et une
     * console ne les reverra jamais. Le chemin PHP, lui, est l'exception
     * exacte : le lock en porte un *déduit* — depuis le web, `PHP_BINARY`
     * désigne le binaire FPM — alors que la commande en cours d'exécution
     * *est* le binaire CLI cherché. Taire ce qu'on constate pour répéter ce
     * qu'on avait déduit ferait afficher « à vérifier » à la seule surface qui
     * n'a rien à vérifier.
     */
    public function withPhpBinary(?PhpBinary $binary): self
    {
        return new self(
            symlink: $this->symlink,
            procOpen: $this->procOpen,
            shellAccess: $this->shellAccess,
            publicIsDocumentRoot: $this->publicIsDocumentRoot,
            webServer: $this->webServer,
            argon2id: $this->argon2id,
            phpBinary: $binary,
        );
    }

    /**
     * Détecte le profil du processus courant.
     *
     * Deux capacités ne se lisent que dans une requête HTTP. En console, elles
     * restent `Unknown` : le mode graphique les renseignera, ou la checklist
     * dira qu'elle ne sait pas — jamais qu'elle sait le contraire.
     *
     * @param  array<string, mixed>  $server  `$_SERVER` de la requête, vide en console.
     */
    public static function detect(string $publicPath, array $server = [], ?string $sapi = null): self
    {
        $sapi ??= PHP_SAPI;
        $console = $sapi === 'cli' || $sapi === 'phpdbg';

        $documentRoot = isset($server['DOCUMENT_ROOT']) && is_string($server['DOCUMENT_ROOT'])
            ? $server['DOCUMENT_ROOT']
            : null;

        $serverSoftware = isset($server['SERVER_SOFTWARE']) && is_string($server['SERVER_SOFTWARE'])
            ? $server['SERVER_SOFTWARE']
            : null;

        return new self(
            symlink: Capability::fromBool(function_exists('symlink')),
            procOpen: Capability::fromBool(function_exists('proc_open')),
            // Si l'on s'exécute en console, c'est qu'un shell existe : la
            // question ne se pose plus. Depuis le web, elle reste entière.
            shellAccess: $console ? Capability::Present : Capability::Unknown,
            publicIsDocumentRoot: $documentRoot === null
                ? Capability::Unknown
                : Capability::fromBool(self::samePath($documentRoot, $publicPath)),
            webServer: WebServer::detect($serverSoftware),
            // Argon2id demande un PHP compilé avec libargon2. Absent, le site
            // tourne en bcrypt — le défaut de Laravel, sûr, mais annoncé
            // plutôt que subi (suivi n° 220).
            argon2id: Capability::fromBool(in_array('argon2id', password_algos(), true)),
            phpBinary: PhpBinary::detect($sapi),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'symlink' => $this->symlink->value,
            'proc_open' => $this->procOpen->value,
            'shell_access' => $this->shellAccess->value,
            'public_is_document_root' => $this->publicIsDocumentRoot->value,
            'web_server' => $this->webServer->value,
            'argon2id' => $this->argon2id->value,
            // `unknown` plutôt que l'absence : `driftFrom()` traite déjà cette
            // valeur comme « on ne sait pas », et une clé toujours présente
            // évite d'avoir à distinguer un lock ancien d'un profil muet.
            'php_binary' => $this->phpBinary->path ?? 'unknown',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            symlink: self::capability($data, 'symlink'),
            procOpen: self::capability($data, 'proc_open'),
            shellAccess: self::capability($data, 'shell_access'),
            publicIsDocumentRoot: self::capability($data, 'public_is_document_root'),
            webServer: is_string($data['web_server'] ?? null)
                ? WebServer::tryFrom($data['web_server']) ?? WebServer::Unknown
                : WebServer::Unknown,
            argon2id: self::capability($data, 'argon2id'),
            phpBinary: is_string($data['php_binary'] ?? null) && $data['php_binary'] !== 'unknown'
                ? PhpBinary::remembered($data['php_binary'])
                : null,
        );
    }

    /**
     * Écarts entre ce profil et un autre, indexés par capacité.
     *
     * Une capacité passée de connue à `Unknown` n'est pas un écart : c'est
     * seulement qu'on l'observe depuis la console alors qu'elle avait été vue
     * depuis le web. Signaler ce cas noierait les vraies dérives.
     *
     * @return array<string, array{avant: string, apres: string}>
     */
    public function driftFrom(self $reference): array
    {
        $drift = [];
        $before = $reference->toArray();

        foreach ($this->toArray() as $key => $value) {
            // `?? 'unknown'` : un lock écrit par une version antérieure ne
            // porte pas les clés ajoutées depuis. Sans ce repli, comparer un
            // profil d'aujourd'hui à un lock d'hier lèverait sur une clé
            // absente — au lieu de dire, comme il se doit, qu'on ne sait pas.
            $avant = $before[$key] ?? 'unknown';

            if ($value === 'unknown' || $avant === 'unknown' || $value === $avant) {
                continue;
            }

            $drift[$key] = ['avant' => $avant, 'apres' => $value];
        }

        return $drift;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function capability(array $data, string $key): Capability
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? Capability::tryFrom($value) ?? Capability::Unknown : Capability::Unknown;
    }

    private static function samePath(string $a, string $b): bool
    {
        $normalise = static fn (string $p): string => rtrim(str_replace(chr(92), '/', $p), '/');

        return $normalise($a) === $normalise($b);
    }
}
