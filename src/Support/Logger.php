<?php

declare(strict_types=1);

namespace Baobab\Support;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Journalisation technique (spec 12 §9) : seul point d'entrée du channel
 * `baobab` — noyau, modules et thèmes journalisent exclusivement via ce
 * service, jamais la façade `Log` directement. Chaque entrée est enrichie
 * d'un contexte structuré obligatoire (`source`/`slug`) et d'un identifiant
 * de requête + de l'utilisateur courant.
 *
 * À distinguer du journal d'audit (`Baobab\Audit\AuditLogger`, spec 04 §7,
 * spec 12 §8) : donnée métier requêtable en base, pas un fichier de log.
 */
final class Logger
{
    private static ?string $requestId = null;

    public function __construct(private readonly LogManager $manager) {}

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        $context += ['source' => 'core', 'slug' => null];
        $context['request_id'] = self::requestId();
        $context['user_id'] = Auth::guard('baobab')->id();

        $this->manager->channel('baobab')->log($level, $message, $context);
    }

    private static function requestId(): string
    {
        return self::$requestId ??= (string) Str::uuid();
    }
}
