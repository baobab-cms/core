<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Ce que l'étape 2 demande (spec 15 §4).
 *
 * `prefix` n'est pas cosmétique : un mutualisé ne donne souvent qu'une seule
 * base, et c'est la réponse de la spec à une base déjà occupée (suivi n° 214).
 */
final readonly class DatabaseCredentials
{
    public function __construct(
        public string $driver,
        public string $database,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $username = null,
        public ?string $password = null,
        public string $prefix = '',
    ) {}

    /**
     * Configuration de connexion Laravel correspondante.
     *
     * @return array<string, mixed>
     */
    public function toConnectionConfig(): array
    {
        if ($this->driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => $this->prefix,
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $this->driver,
            'host' => $this->host ?? '127.0.0.1',
            'port' => $this->port ?? ($this->driver === 'pgsql' ? 5432 : 3306),
            'database' => $this->database,
            'username' => $this->username ?? '',
            'password' => $this->password ?? '',
            'charset' => $this->driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => $this->prefix,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toEnv(): array
    {
        return [
            'DB_CONNECTION' => $this->driver,
            'DB_HOST' => $this->host,
            'DB_PORT' => $this->port,
            'DB_DATABASE' => $this->database,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
            'DB_PREFIX' => $this->prefix,
        ];
    }
}
