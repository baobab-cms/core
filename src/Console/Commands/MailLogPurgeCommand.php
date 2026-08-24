<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Mail\Models\MailLogEntry;
use Illuminate\Console\Command;

/**
 * Purge programmée du journal des e-mails (spec 13 §4.2) — enregistrée
 * quotidiennement dans le scheduler depuis BaobabServiceProvider, patron
 * `seo:purge-404-log`.
 *
 * **Deux rétentions, pas une**, et la seconde est la plus courte : les lignes
 * partent après `log_retention_days` (défaut 90), mais le **corps** rendu,
 * quand il est conservé, est effacé après `log_body_retention_days` (défaut 7)
 * sans que la ligne disparaisse. C'est ce que §4.1 demande en distinguant la
 * trace de l'envoi de son contenu : la première sert le diagnostic sur trois
 * mois, le second n'a d'utilité que le temps de comprendre un incident récent.
 */
final class MailLogPurgeCommand extends Command
{
    protected $signature = 'baobab:mail:purge-log';

    protected $description = 'Purge le journal des e-mails au-delà de la rétention configurée, et efface les corps conservés au-delà de la leur.';

    public function handle(): int
    {
        $retentionDays = (int) config('baobab.mail.log_retention_days', 90);
        $bodyRetentionDays = (int) config('baobab.mail.log_body_retention_days', 7);

        $purged = MailLogEntry::query()
            ->where('created_at', '<=', now()->subDays($retentionDays))
            ->delete();

        $cleared = MailLogEntry::query()
            ->whereNotNull('body')
            ->where('created_at', '<=', now()->subDays($bodyRetentionDays))
            ->update(['body' => null]);

        $this->info("{$purged} entrée(s) du journal des e-mails purgée(s), {$cleared} corps effacé(s).");

        return self::SUCCESS;
    }
}
