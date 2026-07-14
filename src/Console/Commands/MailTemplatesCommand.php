<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\Models\Module;
use Illuminate\Console\Command;

/**
 * Liste les templates d'e-mails déclarés — Core (`config('baobab.mail.templates')`)
 * et modules actifs (`manifest['mails']`, spec 13 §3.1, §6). Aucune notion de
 * personnalisation (§3.2 — M8) : uniquement l'état « déclaré ».
 */
final class MailTemplatesCommand extends Command
{
    protected $signature = 'baobab:mail:templates';

    protected $description = 'Liste les templates d\'e-mails déclarés (Core + modules actifs).';

    public function handle(): int
    {
        $rows = [];

        /** @var list<array<string, mixed>> $coreTemplates */
        $coreTemplates = config('baobab.mail.templates', []);

        foreach ($coreTemplates as $mail) {
            $rows[] = ['core', $mail['key'], $mail['description'] ?? ''];
        }

        foreach (Module::where('status', 'active')->get() as $module) {
            /** @var list<array<string, mixed>> $mails */
            $mails = $module->manifest['mails'] ?? [];

            foreach ($mails as $mail) {
                $rows[] = [$module->name, $mail['key'], $mail['description'] ?? ''];
            }
        }

        if ($rows === []) {
            $this->line('Aucun template d\'e-mail déclaré.');

            return self::SUCCESS;
        }

        $this->table(['Module', 'Clé', 'Description'], $rows);

        return self::SUCCESS;
    }
}
