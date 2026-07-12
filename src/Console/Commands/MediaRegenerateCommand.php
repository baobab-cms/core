<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Models\Media;
use Illuminate\Console\Command;

/**
 * Maintenance des variantes (spec 06 §4.1). Sans option, régénère tous les
 * presets pour tous les médias image (écrase l'existant) ; `--missing` ne
 * touche que ce qui n'a pas encore de variante ; `--preset=` limite à un
 * preset. Dispatche un GenerateMediaConversions par média (queue, cohérent
 * avec la génération à l'upload).
 */
final class MediaRegenerateCommand extends Command
{
    protected $signature = 'media:regenerate {--preset= : Limiter à un preset} {--missing : Ne générer que les variantes absentes}';

    protected $description = 'Régénère les variantes (presets) des médias image.';

    public function handle(): int
    {
        /** @var string|null $preset */
        $preset = $this->option('preset');
        $onlyMissing = (bool) $this->option('missing');

        $count = 0;

        Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->where('mime_type', '!=', 'image/svg+xml')
            ->each(function (Media $media) use ($preset, $onlyMissing, &$count): void {
                GenerateMediaConversions::dispatch($media, $preset, ! $onlyMissing);
                $count++;
            });

        $this->info("{$count} média(s) programmé(s) pour régénération.");

        return self::SUCCESS;
    }
}
