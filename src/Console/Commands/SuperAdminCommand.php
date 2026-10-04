<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Install\Actions\CreateSuperAdmin;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Commande de secours console : crée ou complète un super-admin.
 *
 * **Adaptateur mince, depuis le 25 août 2026** (suivi n° 213). Elle portait
 * jusque-là la logique métier en dur — création de l'utilisateur, rôle,
 * assignation — sans Action, ce qui contredisait la règle de revue n° 1.
 * L'installateur en est devenu le second consommateur ; la logique vit
 * désormais dans `CreateSuperAdmin`, cette commande ne fait plus que la
 * traduire pour un terminal.
 */
final class SuperAdminCommand extends Command
{
    protected $signature = 'baobab:super-admin {email : E-mail de l\'utilisateur}';

    protected $description = 'Crée ou met à jour un super-admin Baobab (commande de secours console).';

    public function handle(CreateSuperAdmin $createSuperAdmin): int
    {
        /** @var string $email */
        $email = $this->argument('email');

        $result = $createSuperAdmin($email);

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Super Admin configuré.</>');
        $this->line("  E-mail : <fg=cyan>{$email}</>");

        if ($result->generatedPassword !== null) {
            $password = OutputFormatter::escape($result->generatedPassword);
            $this->line("  Mot de passe généré : <fg=yellow>{$password}</> (non récupérable)");
        } else {
            $this->line('  <fg=gray>Utilisateur existant — rôle super-admin assigné.</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
