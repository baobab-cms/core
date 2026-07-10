<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

final class SuperAdminCommand extends Command
{
    protected $signature = 'baobab:super-admin {email : E-mail de l\'utilisateur}';

    protected $description = 'Crée ou met à jour un super-admin Baobab (commande de secours console).';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! is_string($email)) {
            throw new InvalidArgumentException('The email argument must be a string.');
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        $generated = false;
        $password = null;

        if ($user === null) {
            $password = Str::password(16);
            $user = User::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => $password,
            ]);
            $generated = true;
        }

        /** @var Role $role */
        $role = Role::findOrCreate('super-admin', 'baobab');

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Super Admin configuré.</>');
        $this->line("  E-mail : <fg=cyan>{$email}</>");

        if ($generated) {
            $this->line("  Mot de passe généré : <fg=yellow>{$password}</> (non récupérable)");
        } else {
            $this->line('  <fg=gray>Utilisateur existant — rôle super-admin assigné.</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
