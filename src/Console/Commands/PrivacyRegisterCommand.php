<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Privacy\Actions\BuildProcessingRegister;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:privacy:register` (spec 16 §7) — dump du registre des
 * traitements en console, même action que l'écran `admin/privacy/register`.
 */
final class PrivacyRegisterCommand extends Command
{
    protected $signature = 'baobab:privacy:register';

    protected $description = 'Affiche le registre des traitements de données personnelles.';

    public function handle(BuildProcessingRegister $build): int
    {
        $register = $build();

        foreach ($register->declarations as $key => $declaration) {
            $this->line("<info>{$declaration->title}</info> ({$key})");
            $this->line('  '.__('baobab::admin.privacy_register.nature').' : '.$declaration->nature);
            $this->line('  '.__('baobab::admin.privacy_register.purpose').' : '.$declaration->purpose);
            $this->line('  '.__('baobab::admin.privacy_register.legal_basis').' : '.$declaration->legalBasis);
            $this->line('  '.__('baobab::admin.privacy_register.retention').' : '.$declaration->retention);
            $this->line('  '.__('baobab::admin.privacy_register.external_services').' : '
                .($declaration->externalServices === [] ? __('baobab::admin.privacy_register.none') : implode(', ', $declaration->externalServices)));
            $this->newLine();
        }

        $this->line('<info>'.__('baobab::admin.privacy_register.recipients_title').'</info>');
        $this->line($register->recipients === []
            ? '  '.__('baobab::admin.privacy_register.no_recipients')
            : '  '.implode(', ', $register->recipients));

        if ($register->undeclaredModules !== []) {
            $this->newLine();
            $this->warn(__('baobab::admin.privacy_register.undeclared_title').' '.implode(', ', $register->undeclaredModules));
        }

        return self::SUCCESS;
    }
}
