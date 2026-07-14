<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Support\PlaceholderRenderer;
use Baobab\Modules\ModuleManifest;

/**
 * Validation sémantique des templates d'e-mails déclarés au manifeste (spec 13
 * §3.1) — au-delà de la conformité structurelle déjà vérifiée par
 * `ManifestValidator` : toute variable `required` doit apparaître dans le
 * sujet/corps par défaut du template, sans quoi l'e-mail serait impossible à
 * personnaliser sans le casser (spec 13 §3.3, §7 décision 3). Appelée par
 * `InstallModule`, avant toute écriture en base — échoue fort, comme le reste
 * du manifeste.
 */
final class MailTemplateValidator
{
    public function __construct(private readonly PlaceholderRenderer $renderer) {}

    public function assertValid(ModuleManifest $manifest, string $modulePath): void
    {
        foreach ($manifest->mails() as $mail) {
            $this->assertRequiredVariablesPresent($mail, $modulePath);
        }
    }

    /**
     * @param  array{key: string, variables: array<string, mixed>, defaults: string}  $mail
     */
    private function assertRequiredVariablesPresent(array $mail, string $modulePath): void
    {
        $defaultsPath = rtrim($modulePath, '/').'/'.ltrim($mail['defaults'], '/');

        if (! is_file($defaultsPath)) {
            throw InvalidMailTemplateException::missingDefaults($mail['key'], $mail['defaults']);
        }

        /** @var array{subject?: string, body?: string} $defaults */
        $defaults = json_decode((string) file_get_contents($defaultsPath), true, flags: JSON_THROW_ON_ERROR);

        $placeholders = $this->renderer->placeholdersIn(($defaults['subject'] ?? '').' '.($defaults['body'] ?? ''));

        foreach ($mail['variables'] as $name => $definition) {
            $required = is_array($definition) && ($definition['required'] ?? false);

            if ($required && ! in_array($name, $placeholders, true)) {
                throw InvalidMailTemplateException::missingRequiredVariable($mail['key'], $name);
            }
        }
    }
}
