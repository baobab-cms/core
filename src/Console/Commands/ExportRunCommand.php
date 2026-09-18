<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Exports\Models\ExportJob;
use Baobab\System\Actions\ExportContent;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:export --types=a,b,c` (spec 12 §5.2, cadrage Pass F1,
 * suivi n° 326) — invoque `ExportContent` directement, jamais via une mise
 * en file : un export CLI doit être terminé avant que la commande ne rende
 * la main, patron exact `baobab:backup` → `CreateBackup`. L'admin, lui,
 * passe par `RunContentExportJob` sur `baobab-low` (§12 décision 9).
 */
final class ExportRunCommand extends Command
{
    protected $signature = 'baobab:export {--types= : Clés des content types à exporter, séparées par des virgules}';

    protected $description = 'Exporte une sélection de content types en archive .zip portable.';

    public function handle(ExportContent $action): int
    {
        /** @var string|null $option */
        $option = $this->option('types');
        $keys = array_values(array_filter(array_map('trim', explode(',', (string) $option))));

        if ($keys === []) {
            $this->error('Aucun content type indiqué (--types=a,b,c).');

            return self::FAILURE;
        }

        $unknown = array_values(array_diff($keys, ContentType::query()->whereIn('key', $keys)->pluck('key')->all()));

        if ($unknown !== []) {
            $this->error('Content type(s) inconnu(s) : '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $exportJob = ExportJob::create([
            'status' => 'pending',
            'content_type_keys' => $keys,
        ]);

        if (! $action($exportJob)) {
            $this->error("Export failed : {$exportJob->error_message}");

            return self::FAILURE;
        }

        $this->info("Export completed: {$exportJob->file_path}");

        return self::SUCCESS;
    }
}
