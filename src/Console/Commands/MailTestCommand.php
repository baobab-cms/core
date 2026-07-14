<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Mail\Actions\SendTestMail;
use Illuminate\Console\Command;

/**
 * Équivalent CLI de l'action `SendTestMail` (spec 13 §2.1, §6).
 */
final class MailTestCommand extends Command
{
    protected $signature = 'baobab:mail:test {address : Adresse e-mail destinataire}';

    protected $description = 'Envoie un e-mail de test.';

    public function handle(): int
    {
        $address = (string) $this->argument('address');

        app(SendTestMail::class)($address);

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ E-mail de test mis en file d\'attente.</>');
        $this->line("  Destinataire : <fg=cyan>{$address}</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
