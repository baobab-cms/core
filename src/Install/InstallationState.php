<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * État d'installation de l'instance (spec 15 §3 et §4, dernier paragraphe).
 *
 * Deux fichiers, deux rôles à ne pas confondre :
 *
 * - `installed.lock` — la sentinelle. Sa seule présence dit « ce site est
 *   installé » ; les routes `/install/*` ne sont alors plus enregistrées du
 *   tout, il n'y a pas de « page désactivée ». Il porte aussi le profil
 *   d'hébergement, que `baobab:check` relira pour mesurer la dérive.
 * - `install-state.json` — l'avancement, et lui seul. Il permet à une
 *   installation interrompue — coupure, timeout d'un mutualisé — de reprendre
 *   à l'étape échouée plutôt qu'au début. Il est supprimé à la finalisation :
 *   sa persistance après coup n'aurait aucun sens et donnerait deux sources de
 *   vérité sur le même fait.
 */
final readonly class InstallationState
{
    public function __construct(
        private Filesystem $files,
        private string $directory,
    ) {}

    public function isInstalled(): bool
    {
        return $this->files->isFile($this->lockPath());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lock(): ?array
    {
        if (! $this->isInstalled()) {
            return null;
        }

        return $this->readJson($this->lockPath());
    }

    /**
     * Le profil journalisé à l'installation, si l'instance est installée.
     */
    public function installedProfile(): ?HostingProfile
    {
        $lock = $this->lock();
        $profile = $lock['profile'] ?? null;

        return is_array($profile) ? HostingProfile::fromArray($profile) : null;
    }

    /**
     * Écrit la sentinelle et efface l'avancement — dans cet ordre.
     *
     * L'ordre n'est pas indifférent : si l'écriture du lock échoue, on veut
     * que l'avancement survive pour que la reprise sache où elle en était.
     * L'inverse laisserait une installation qui redémarre de zéro.
     */
    public function markInstalled(string $version, HostingProfile $profile, string $configChecksum): void
    {
        $this->writeJson($this->lockPath(), [
            'version' => $version,
            'installed_at' => date(DATE_ATOM),
            'config_checksum' => $configChecksum,
            'profile' => $profile->toArray(),
        ]);

        $this->files->delete($this->statePath());
    }

    /**
     * Note qu'une étape est passée, pour que la reprise ne la rejoue pas.
     */
    public function recordStep(string $step): void
    {
        $steps = $this->completedSteps();

        if (in_array($step, $steps, true)) {
            return;
        }

        $steps[] = $step;
        $this->writeJson($this->statePath(), ['completed' => $steps, 'updated_at' => date(DATE_ATOM)]);
    }

    /**
     * @return list<string>
     */
    public function completedSteps(): array
    {
        $state = $this->readJson($this->statePath());
        $completed = $state['completed'] ?? [];

        if (! is_array($completed)) {
            return [];
        }

        return array_values(array_filter($completed, 'is_string'));
    }

    public function hasCompleted(string $step): bool
    {
        return in_array($step, $this->completedSteps(), true);
    }

    /**
     * Repart de zéro. Ne touche pas au lock : désinstaller n'est pas le sujet
     * de cette classe, et un lock effacé par mégarde rouvrirait l'installateur
     * sur un site en production.
     */
    public function forgetProgress(): void
    {
        $this->files->delete($this->statePath());
    }

    public function lockPath(): string
    {
        return $this->directory.'/installed.lock';
    }

    public function statePath(): string
    {
        return $this->directory.'/install-state.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! $this->files->isFile($path)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $this->files->ensureDirectoryExists($this->directory);
        $this->files->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}
