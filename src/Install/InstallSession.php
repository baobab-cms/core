<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Contracts\Session\Session;
use Illuminate\Filesystem\Filesystem;

/**
 * Session d'installation et verrou exclusif — spec 15 §6.2.
 *
 * Le §6.2 demande « une session d'installation signée, verrou exclusif (une
 * seule session d'installation à la fois) ». Les deux moitiés répondent à des
 * risques différents et se lisent séparément.
 *
 * **La session** dit « ce navigateur a présenté le jeton ». Elle vit dans la
 * session Laravel, qui est déjà signée et chiffrée par le framework : rien à
 * réinventer, et surtout rien à signer soi-même (*Laravel-first*).
 *
 * **Le verrou** dit « et il est le seul ». Il vit sur le disque, parce qu'une
 * session ne sait rien des autres sessions. Il retient un identifiant tiré au
 * sort, jamais l'adresse IP : une IP change en cours de route sur un réseau
 * mobile, et derrière un proxy elle est de toute façon déclarative — le suivi
 * n° 176 rappelle qu'un `X-Forwarded-For` se falsifie. Un verrou qu'on perd en
 * changeant de wifi, ou qu'un tiers revendique en forgeant un en-tête, ne
 * protège personne.
 *
 * **Le verrou expire, et c'est essentiel.** Un onglet fermé au milieu d'une
 * installation ne doit pas condamner le site : sans expiration, la seule
 * sortie serait de supprimer un fichier sur le serveur — précisément ce que
 * l'utilisateur d'un mutualisé fait le plus mal. Trente minutes d'inactivité,
 * recalculées à chaque requête de la session détentrice.
 */
final class InstallSession
{
    private const SESSION_KEY = 'baobab.install.session_id';

    private const HOLDER_TTL_SECONDS = 1800;

    public function __construct(
        private readonly Filesystem $files,
        private readonly Session $session,
        private readonly string $path,
    ) {}

    /**
     * Le jeton a été présenté et accepté par ce navigateur.
     */
    public function isAuthenticated(): bool
    {
        return is_string($this->session->get(self::SESSION_KEY));
    }

    /**
     * Ouvre la session d'installation et prend le verrou.
     *
     * Rend `false` si un autre navigateur le détient encore : l'appelant en
     * fait un message, jamais une exception — se voir refuser l'entrée est un
     * cas normal, pas une panne.
     */
    public function claim(): bool
    {
        $holder = $this->currentHolder();

        if ($holder !== null && $holder !== $this->sessionId()) {
            return false;
        }

        $id = $this->sessionId() ?? bin2hex(random_bytes(16));

        $this->session->put(self::SESSION_KEY, $id);
        $this->write($id);

        return true;
    }

    /**
     * Rafraîchit le verrou tant que la session travaille.
     *
     * Rend `false` si le verrou a été repris entre-temps — cas d'une session
     * laissée en veille au-delà de l'expiration, puis reprise alors que
     * quelqu'un d'autre a commencé. L'appelant doit alors refuser, faute de
     * quoi deux installations écriraient dans le même `.env`.
     */
    public function touch(): bool
    {
        $id = $this->sessionId();

        if ($id === null) {
            return false;
        }

        $holder = $this->currentHolder();

        if ($holder !== null && $holder !== $id) {
            return false;
        }

        $this->write($id);

        return true;
    }

    /**
     * Ferme la session et rend le verrou — autodestruction, ou abandon
     * explicite. Rejouable sans conséquence.
     */
    public function release(): void
    {
        $this->session->forget(self::SESSION_KEY);

        if ($this->files->isFile($this->path)) {
            $this->files->delete($this->path);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    private function sessionId(): ?string
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_string($id) ? $id : null;
    }

    /**
     * Détenteur courant du verrou, ou `null` s'il est libre ou périmé.
     *
     * Un fichier illisible ou corrompu est traité comme un verrou libre : sur
     * un mutualisé, une écriture interrompue arrive, et bloquer l'installation
     * sur un JSON tronqué serait une panne sans issue pour l'utilisateur.
     */
    private function currentHolder(): ?string
    {
        if (! $this->files->isFile($this->path)) {
            return null;
        }

        $payload = json_decode((string) $this->files->get($this->path), true);

        if (! is_array($payload)) {
            return null;
        }

        $id = $payload['id'] ?? null;
        $seenAt = $payload['seen_at'] ?? null;

        if (! is_string($id) || ! is_int($seenAt)) {
            return null;
        }

        if ($seenAt + self::HOLDER_TTL_SECONDS < time()) {
            return null;
        }

        return $id;
    }

    private function write(string $id): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, (string) json_encode([
            'id' => $id,
            'seen_at' => time(),
        ], JSON_PRETTY_PRINT));
    }
}
