<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Contracts\Session\Session;

/**
 * Ce que le wizard a collecté, d'un écran à l'autre — suivi n° 224.
 *
 * **Pourquoi ça existe.** La console construit son `InstallationInput` d'un
 * bloc, en fin d'invite. Le navigateur le collecte sur plusieurs écrans, et le
 * §6.1 exige « le retour arrière possible avant finalisation » : il faut donc
 * accumuler d'abord et exécuter ensuite, faute de quoi revenir en arrière
 * n'aurait aucun sens — on ne revient pas sur des migrations passées ni sur un
 * compte créé.
 *
 * **Pourquoi la session, et pas le fichier d'état.** Ce brouillon porte **deux
 * mots de passe**, celui de la base et celui de l'administrateur. La session
 * Laravel est déjà signée, chiffrée et stockée hors racine web ; c'est le
 * mécanisme prévu pour un formulaire multi-écrans, et c'est déjà celle qui
 * porte le verrou exclusif de la C1. Écrire ces secrets dans
 * `install-state.json` transformerait un fichier de **diagnostic** — qu'on
 * lit, qu'on envoie au support, qui finit dans une sauvegarde — en fichier de
 * secrets ; le suivi n° 217 avait justement établi que le lock n'en porte
 * aucun. Arbitrage tranché le 28 août 2026.
 *
 * Conséquence assumée : une session perdue perd le brouillon. L'installation,
 * elle, ne perd rien — l'avancement réel vit dans `install-state.json`, et les
 * étapes déjà passées ne se rejouent pas.
 */
final class InstallDraft
{
    public const SECTION_DATABASE = 'database';

    public const SECTION_ACCOUNT = 'account';

    public const SECTION_SITE = 'site';

    private const KEY = 'baobab.install.draft';

    public function __construct(private readonly Session $session) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(string $section, array $values): void
    {
        $draft = $this->all();
        $draft[$section] = $values;

        $this->session->put(self::KEY, $draft);
    }

    /**
     * @return array<string, mixed>
     */
    public function section(string $section): array
    {
        $values = $this->all()[$section] ?? [];

        return is_array($values) ? $values : [];
    }

    public function has(string $section): bool
    {
        return $this->section($section) !== [];
    }

    /**
     * @return list<string>
     */
    public function missingSections(): array
    {
        return array_values(array_filter(
            [self::SECTION_DATABASE, self::SECTION_ACCOUNT, self::SECTION_SITE],
            fn (string $section): bool => ! $this->has($section),
        ));
    }

    public function isComplete(): bool
    {
        return $this->missingSections() === [];
    }

    /**
     * Efface le brouillon — à la finalisation, ou sur abandon explicite.
     *
     * **Ce n'est pas de l'hygiène, c'est la fin de vie de deux mots de passe.**
     * Les laisser dans la session après l'installation les ferait vivre aussi
     * longtemps que le fichier de session, sans qu'aucun écran ne les relise.
     */
    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * Assemble l'entrée du pipeline à partir de ce qui a été collecté.
     *
     * Appelée seulement quand `isComplete()` est vrai — l'appelant a la
     * réponse avant de demander, et un brouillon incomplet est un défaut de
     * parcours, pas un cas d'exécution.
     */
    public function toInput(string $version, bool $optimize = true): InstallationInput
    {
        $database = $this->section(self::SECTION_DATABASE);
        $account = $this->section(self::SECTION_ACCOUNT);
        $site = $this->section(self::SECTION_SITE);

        return new InstallationInput(
            database: new DatabaseCredentials(
                driver: $this->string($database, 'driver', 'mysql'),
                database: $this->string($database, 'database'),
                host: $this->string($database, 'host', '127.0.0.1'),
                port: ($port = $this->string($database, 'port')) === '' ? null : (int) $port,
                username: $this->string($database, 'username'),
                password: $this->string($database, 'password'),
                prefix: $this->string($database, 'prefix'),
            ),
            adminEmail: $this->string($account, 'email'),
            siteName: $this->string($site, 'name'),
            url: $this->string($site, 'url'),
            adminName: ($name = $this->string($account, 'name')) === '' ? null : $name,
            adminPassword: ($password = $this->string($account, 'password')) === '' ? null : $password,
            timezone: $this->string($site, 'timezone', 'UTC'),
            registrationOpen: (bool) ($site['registration_open'] ?? false),
            version: $version,
            optimize: $optimize,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        $draft = $this->session->get(self::KEY, []);

        return is_array($draft) ? $draft : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function string(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
