<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Notify\Notifier;
use Baobab\Users\Models\User;
use Illuminate\Console\Command;

/**
 * Envoi de test d'une notification déclarée (spec 11 §10).
 */
final class NotifyTestCommand extends Command
{
    protected $signature = 'baobab:notify:test {key : Clé de la notification déclarée} {user : ID ou e-mail du destinataire}';

    protected $description = 'Envoie une notification de test à un utilisateur.';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $identifier = (string) $this->argument('user');

        $user = is_numeric($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();

        if ($user === null) {
            $this->error("Utilisateur introuvable : {$identifier}");

            return self::FAILURE;
        }

        app(Notifier::class)->send($key, [$user]);

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Notification de test envoyée.</>');
        $this->line("  Clé : <fg=cyan>{$key}</> — Destinataire : <fg=cyan>{$user->email}</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
