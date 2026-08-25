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
    ) {}

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
            if ($value === 'unknown' || $before[$key] === 'unknown' || $value === $before[$key]) {
                continue;
            }

            $drift[$key] = ['avant' => $before[$key], 'apres' => $value];
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
