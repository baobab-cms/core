<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Contracts\MailSampleProvider;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Support\MailTemplateVariables;
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
            $this->assertDeclaredClassesHonourContracts($mail);
        }
    }

    /**
     * `resolver` et `sample` (§3.1) portent des noms de classe : les vérifier
     * ici, c'est refuser l'installation plutôt que découvrir la faute au
     * premier clic sur « Renvoyer » ou sur « Aperçu » — deux endroits où
     * l'erreur serait attribuée au Core et non au module qui l'a déclarée.
     *
     * @param  array{key: string, resolver?: class-string, sample?: class-string}  $mail
     */
    private function assertDeclaredClassesHonourContracts(array $mail): void
    {
        /** @var array<string, class-string> $contracts */
        $contracts = [
            'resolver' => MailDataResolver::class,
            'sample' => MailSampleProvider::class,
        ];

        foreach ($contracts as $attribute => $contract) {
            $class = $mail[$attribute] ?? null;

            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                throw InvalidMailTemplateException::unknownClass($mail['key'], $attribute, $class);
            }

            if (! is_subclass_of($class, $contract)) {
                throw InvalidMailTemplateException::wrongContract($mail['key'], $attribute, $class, $contract);
            }
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

        /** @var array<string, string|array{label: string, required?: bool}> $declared */
        $declared = $mail['variables'];

        $missing = (new MailTemplateVariables($declared))->missingRequiredIn($placeholders);

        if ($missing !== []) {
            throw InvalidMailTemplateException::missingRequiredVariable($mail['key'], $missing[0]);
        }
    }
}
