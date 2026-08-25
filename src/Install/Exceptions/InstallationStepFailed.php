<?php

declare(strict_types=1);

namespace Baobab\Install\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Échec d'une étape d'installation, **nommé et lisible** (spec 15 §4).
 *
 * Même règle qu'au Studio (suivi n° 206) : le message nomme l'étape et le
 * remède, et ne porte **ni SQL, ni trace** — la cause reste en `previous`
 * pour le journal. La personne devant l'écran d'installation est, par
 * définition, celle qui n'a ni shell ni documentation ; lui servir un message
 * de PDO reviendrait à l'arrêter là.
 */
final class InstallationStepFailed extends RuntimeException
{
    private function __construct(
        public readonly string $step,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function database(string $message, ?Throwable $previous = null): self
    {
        return new self('database', $message, $previous);
    }

    public static function migrations(string $message, ?Throwable $previous = null): self
    {
        return new self('migrations', $message, $previous);
    }

    public static function finalization(string $message, ?Throwable $previous = null): self
    {
        return new self('finalization', $message, $previous);
    }
}
