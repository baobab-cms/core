<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\Models\Module;
use Baobab\Themes\Actions\GeneratePreviewLink;
use Illuminate\Console\Command;

final class ThemePreviewCommand extends Command
{
    protected $signature = 'baobab:theme:preview {name : The theme name (vendor/slug)}';

    protected $description = 'Print a signed link that previews an installed theme without activating it.';

    public function handle(GeneratePreviewLink $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $theme = Module::where('name', $name)->where('type', 'theme')->first();

        if (! $theme instanceof Module) {
            $this->error("Theme not found or not a theme: {$name}.");

            return self::FAILURE;
        }

        $this->info($action($theme));

        return self::SUCCESS;
    }
}
