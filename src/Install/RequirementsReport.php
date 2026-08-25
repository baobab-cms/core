<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Résultat de l'étape 1 (spec 15 §4) : ce qui bloque, ce qui avertit, et le
 * profil que les étapes suivantes consommeront (§4.1).
 *
 * Les exigences et le profil voyagent **ensemble** parce qu'ils sont produits
 * du même geste : ce que l'hébergement ne sait pas faire décide autant de
 * l'arrêt que de l'adaptation.
 */
final readonly class RequirementsReport
{
    /**
     * @param  list<Requirement>  $requirements
     */
    public function __construct(
        public array $requirements,
        public HostingProfile $profile,
    ) {}

    public function passes(): bool
    {
        return $this->blockingFailures() === [];
    }

    /**
     * @return list<Requirement>
     */
    public function blockingFailures(): array
    {
        return array_values(array_filter(
            $this->requirements,
            static fn (Requirement $r): bool => $r->isBlockingFailure(),
        ));
    }

    /**
     * @return list<Requirement>
     */
    public function advisories(): array
    {
        return array_values(array_filter(
            $this->requirements,
            static fn (Requirement $r): bool => ! $r->blocking && ! $r->satisfied,
        ));
    }

    public function get(string $key): ?Requirement
    {
        foreach ($this->requirements as $requirement) {
            if ($requirement->key === $key) {
                return $requirement;
            }
        }

        return null;
    }
}
