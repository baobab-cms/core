<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleDiscovery;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all installed modules and discovered (not yet installed) ones.';

    public function handle(ModuleDiscovery $discovery): int
    {
        $installed = Module::all()->keyBy('name');

        $rows = $installed->map(fn (Module $m) => [
            $m->name,
            $m->title,
            $m->version,
            $m->type,
            $m->status,
        ])->values()->toArray();

        $discovered = $discovery->scan()->filter(
            fn ($d) => ! $installed->has($d->manifest->name())
        );

        foreach ($discovered as $d) {
            $rows[] = [
                $d->manifest->name(),
                $d->manifest->title(),
                $d->manifest->version(),
                $d->manifest->type(),
                '<comment>discovered</comment>',
            ];
        }

        if (empty($rows)) {
            $this->info('No modules found.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Title', 'Version', 'Type', 'Status'], $rows);

        return self::SUCCESS;
    }
}
