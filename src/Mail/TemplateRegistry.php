<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\Support\MailTemplateVariables;
use Baobab\Modules\Models\Module;

/**
 * Résout une clé de template (`core.*` ou `{slug}.*`) et énumère ce qui est
 * déclaré (spec 13 §3.1). Le Core n'étant pas une ligne de la table `modules`,
 * ses propres templates sont déclarés dans `config('baobab.mail.templates')`
 * plutôt que dans un manifest ; les modules actifs sont lus depuis leur
 * `manifest['mails']`, comme `SchedulerRegistrar` le fait déjà pour `schedule`.
 *
 * **Point unique d'énumération** (patron `ModuleInventory`, suivi n° 113) : la
 * CLI `baobab:mail:templates` et l'écran `admin/mails` (Pass A2) consomment
 * `all()` au lieu de refaire la boucle « config + modules actifs » chacun de
 * son côté — un écran qui listerait autre chose que la CLI serait un second
 * chemin de code.
 *
 * `find()` applique la personnalisation admin (§3.2) ; `default()` donne le
 * défaut du code seul, dont la restauration et le diff ont besoin.
 */
final class TemplateRegistry
{
    /**
     * Le template tel qu'il sera envoyé : défaut du code, recouvert par la
     * personnalisation admin quand elle existe.
     */
    public function find(string $key): MailTemplate
    {
        $default = $this->default($key);
        $override = MailTemplateOverride::query()->where('key', $key)->first();

        if ($override === null) {
            return $default;
        }

        return new MailTemplate(
            key: $key,
            subject: $override->subject,
            body: $override->body,
            fromAddress: $override->from_address,
            fromName: $override->from_name,
            customised: true,
        );
    }

    /**
     * Le défaut livré par le code, personnalisation ignorée — source de la
     * restauration (§3.2) et base de comparaison du diff.
     */
    public function default(string $key): MailTemplate
    {
        $defaults = $this->readDefaults($this->declaration($key)->defaultsPath);

        return new MailTemplate($key, $defaults['subject'], $defaults['body']);
    }

    /**
     * Le défaut du code a-t-il changé depuis que l'admin a enregistré sa
     * version ? `null` quand le template n'est pas personnalisé — il n'y a
     * alors rien à préserver, la nouvelle version *est* ce qui sera envoyé —
     * et `null` aussi quand le défaut n'a pas bougé.
     */
    public function defaultDrift(string $key): ?MailTemplateDrift
    {
        $override = MailTemplateOverride::query()->where('key', $key)->first();

        if ($override === null) {
            return null;
        }

        $snapshot = $override->default_snapshot;
        $current = $this->default($key);

        if ($snapshot['subject'] === $current->subject && $snapshot['body'] === $current->body) {
            return null;
        }

        return new MailTemplateDrift(
            key: $key,
            previousSubject: $snapshot['subject'],
            previousBody: $snapshot['body'],
            currentSubject: $current->subject,
            currentBody: $current->body,
        );
    }

    /**
     * Les clés effectivement personnalisées, en une requête — ce dont une
     * *liste* a besoin, là où `find()` relirait le fichier de défauts de
     * chaque template juste pour connaître son état.
     *
     * @return list<string>
     */
    public function customisedKeys(): array
    {
        /** @var list<string> $keys */
        $keys = MailTemplateOverride::query()->pluck('key')->all();

        return $keys;
    }

    public function declaration(string $key): MailTemplateDeclaration
    {
        foreach ($this->all() as $declaration) {
            if ($declaration->key === $key) {
                return $declaration;
            }
        }

        throw MailTemplateNotFoundException::forKey($key);
    }

    /**
     * Tout ce qui est déclaré : Core d'abord, puis les modules actifs.
     *
     * @return list<MailTemplateDeclaration>
     */
    public function all(): array
    {
        /** @var list<array<string, mixed>> $coreTemplates */
        $coreTemplates = config('baobab.mail.templates', []);

        $declarations = [];

        foreach ($coreTemplates as $mail) {
            $declarations[] = $this->declare($mail, 'core', (string) $mail['defaults']);
        }

        foreach (Module::where('status', 'active')->get() as $module) {
            /** @var list<array<string, mixed>> $mails */
            $mails = $module->manifest['mails'] ?? [];

            foreach ($mails as $mail) {
                $path = rtrim((string) $module->path, '/').'/'.ltrim((string) $mail['defaults'], '/');

                $declarations[] = $this->declare($mail, (string) $module->name, $path);
            }
        }

        return $declarations;
    }

    /**
     * @param  array<string, mixed>  $mail
     */
    private function declare(array $mail, string $source, string $defaultsPath): MailTemplateDeclaration
    {
        /** @var array<string, string|array{label: string, required?: bool}> $variables */
        $variables = $mail['variables'] ?? [];

        /** @var class-string|null $resolver */
        $resolver = $mail['resolver'] ?? null;

        /** @var class-string|null $sample */
        $sample = $mail['sample'] ?? null;

        return new MailTemplateDeclaration(
            key: (string) $mail['key'],
            source: $source,
            description: (string) ($mail['description'] ?? ''),
            variables: new MailTemplateVariables($variables),
            defaultsPath: $defaultsPath,
            resolver: $resolver,
            sample: $sample,
        );
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function readDefaults(string $path): array
    {
        /** @var array{subject: string, body: string} $defaults */
        $defaults = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $defaults;
    }
}
