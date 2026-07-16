<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Themes\Actions\ActivateTheme;
use Illuminate\Console\Command;
use Throwable;

final class ThemeActivateCommand extends Command
{
    protected $signature = 'theme:activate {name : The theme name (vendor/slug)}';

    protected $description = 'Activate an installed theme, deactivating the currently active one.';

    public function handle(ActivateTheme $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $theme = $action($name);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Theme [{$theme->name}] activated.");

        return self::SUCCESS;
    }
}
