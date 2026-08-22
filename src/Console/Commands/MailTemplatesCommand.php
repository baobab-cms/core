<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Mail\TemplateRegistry;
use Illuminate\Console\Command;

/**
 * Liste les templates d'e-mails déclarés — Core (`config('baobab.mail.templates')`)
 * et modules actifs (`manifest['mails']`, spec 13 §3.1, §6), avec leur état
 * « défaut / personnalisé » (§3.2).
 *
 * L'énumération vient de `TemplateRegistry::all()` et n'est plus refaite ici :
 * l'écran `admin/mails` liste la même chose, et deux boucles parallèles
 * finiraient par ne plus dire la même chose (patron `ModuleInventory`, n° 113).
 */
final class MailTemplatesCommand extends Command
{
    protected $signature = 'baobab:mail:templates';

    protected $description = 'Liste les templates d\'e-mails déclarés (Core + modules actifs) et leur état.';

    public function handle(TemplateRegistry $templates): int
    {
        $rows = [];
        $customised = $templates->customisedKeys();

        foreach ($templates->all() as $declaration) {
            $rows[] = [
                $declaration->source,
                $declaration->key,
                in_array($declaration->key, $customised, true) ? 'personnalisé' : 'défaut',
                $declaration->description,
            ];
        }

        if ($rows === []) {
            $this->line('Aucun template d\'e-mail déclaré.');

            return self::SUCCESS;
        }

        $this->table(['Module', 'Clé', 'État', 'Description'], $rows);

        return self::SUCCESS;
    }
}
