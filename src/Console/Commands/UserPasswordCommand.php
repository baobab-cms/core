<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Auth\Actions\SetUserPassword;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Exceptions\UserNotFoundException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function Laravel\Prompts\password;

/**
 * Commande de secours du mot de passe (spec 05 §6.1, suivi n° 362) : change le
 * mot de passe d'un utilisateur existant sans passer par l'e-mail.
 *
 * Adaptateur mince de `SetUserPassword`. Le mot de passe ne passe **jamais**
 * en argument (historique du shell, liste des processus — suivi n° 218) : il
 * vient d'une invite masquée avec confirmation, de la variable d'environnement
 * nommée par `--password-env`, ou il est forgé (`--generate`, ou par défaut en
 * mode non interactif sans variable) et affiché une seule fois.
 */
final class UserPasswordCommand extends Command
{
    protected $signature = 'baobab:user:password
        {email : E-mail de l\'utilisateur}
        {--password-env= : Variable d\'environnement portant le nouveau mot de passe}
        {--generate : Forger un mot de passe et l\'afficher une seule fois}';

    protected $description = 'Change le mot de passe d\'un utilisateur existant (commande de secours console).';

    public function handle(SetUserPassword $setPassword): int
    {
        /** @var string $email */
        $email = $this->argument('email');
        $variable = $this->option('password-env');
        $variable = is_string($variable) && $variable !== '' ? $variable : null;

        if ($variable !== null && $this->option('generate')) {
            $this->components->error('--password-env et --generate s\'excluent : choisissez l\'un ou l\'autre.');

            return self::FAILURE;
        }

        $new = null;

        if ($variable !== null) {
            $value = getenv($variable);

            if ($value === false || $value === '') {
                $this->components->error("La variable d'environnement « {$variable} » n'est pas définie.");

                return self::FAILURE;
            }

            $new = $value;
        } elseif (! $this->option('generate') && $this->input->isInteractive()) {
            $new = $this->promptPassword();
        }

        try {
            $result = $setPassword($email, $new);
        } catch (UserNotFoundException) {
            $this->components->error("Aucun compte pour {$email}. Cette commande ne crée pas de compte : voir baobab:super-admin.");

            return self::FAILURE;
        } catch (InvalidAccountStateException) {
            $this->components->error("Le compte {$email} est désactivé : réactivez-le d'abord (il choisira alors un nouveau mot de passe).");

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->components->error('Mot de passe refusé : '.$e->validator->errors()->first('password'));

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Mot de passe changé.</>');
        $this->line("  Compte : <fg=cyan>{$email}</>");

        if ($result->generatedPassword !== null) {
            $password = OutputFormatter::escape($result->generatedPassword);
            $this->line("  Mot de passe généré : <fg=yellow>{$password}</> (non récupérable)");
        }

        $this->line('  <fg=gray>Sessions fermées ; 2FA et tokens API conservés.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Invite masquée, redemandée tant que la politique n'est pas tenue, puis
     * confirmation : une faute de frappe ne doit pas verrouiller le compte.
     */
    private function promptPassword(): string
    {
        $first = password(
            label: 'Nouveau mot de passe',
            validate: function (string $value): ?string {
                $validator = Validator::make(['password' => $value], ['password' => ['required', 'string', PasswordRule::defaults()]]);

                return $validator->fails() ? $validator->errors()->first('password') : null;
            },
        );

        password(
            label: 'Confirmez le mot de passe',
            validate: fn (string $value): ?string => $value === $first ? null : 'Les deux saisies ne correspondent pas.',
        );

        return $first;
    }
}
