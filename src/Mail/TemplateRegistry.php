<?php

declare(strict_types=1);

namespace Baobab\Mail;

use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Modules\Models\Module;

/**
 * Résout une clé de template (`core.*` ou `{slug}.*`) vers son sujet/corps par
 * défaut (spec 13 §3.1). Le Core n'étant pas une ligne de la table `modules`,
 * ses propres templates sont déclarés dans `config('baobab.mail.templates')`
 * plutôt que dans un manifest ; les modules actifs sont lus depuis leur
 * `manifest['mails']`, comme `SchedulerRegistrar` le fait déjà pour `schedule`.
 * Pas de lookup de personnalisation admin (`mail_templates`, §3.2 — M8).
 */
final class TemplateRegistry
{
    public function find(string $key): MailTemplate
    {
        $declaration = str_starts_with($key, 'core.')
            ? $this->findCoreDeclaration($key)
            : $this->findModuleDeclaration($key);

        if ($declaration === null) {
            throw MailTemplateNotFoundException::forKey($key);
        }

        [$defaultsPath, $mail] = $declaration;

        /** @var array{subject: string, body: string} $defaults */
        $defaults = json_decode((string) file_get_contents($defaultsPath), true, flags: JSON_THROW_ON_ERROR);

        return new MailTemplate($mail['key'], $defaults['subject'], $defaults['body']);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function findCoreDeclaration(string $key): ?array
    {
        /** @var list<array<string, mixed>> $templates */
        $templates = config('baobab.mail.templates', []);

        foreach ($templates as $mail) {
            if ($mail['key'] === $key) {
                return [$mail['defaults'], $mail];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function findModuleDeclaration(string $key): ?array
    {
        foreach (Module::where('status', 'active')->get() as $module) {
            /** @var list<array<string, mixed>> $mails */
            $mails = $module->manifest['mails'] ?? [];

            foreach ($mails as $mail) {
                if ($mail['key'] === $key) {
                    $path = rtrim((string) $module->path, '/').'/'.ltrim($mail['defaults'], '/');

                    return [$path, $mail];
                }
            }
        }

        return null;
    }
}
